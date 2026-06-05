<?php
session_start();

// Trava de segurança: impede acessos de usuários deslogados
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../views/login.php");
    exit;
}

require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/ia_controller.php'; // Inclui a função do Llama 3.3

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $usuario_id    = $_SESSION['usuario_id'];
    $data_consumo  = $_POST['data_refeicao']; 
    $tipo_refeicao = $_POST['tipo_refeicao']; 
    $refeicao_id   = $_POST['refeicao_id'] ?? null;
    $acao          = $_POST['acao'] ?? 'salvar';
    
    // Trava de segurança no Backend: Impede injeções manuais em datas futuras
    if (strtotime($data_consumo) > strtotime(date('Y-m-d'))) {
        header("Location: ../views/dashboard.php?erro=data_futura");
        exit;
    }

    // ==========================================
    // OPERAÇÃO DE EXCLUSÃO DE REFEIÇÃO
    // ==========================================
    if ($acao === 'excluir' && !empty($refeicao_id)) {
        try {
            $pdo->beginTransaction();

            // 1. Exclui primeiro os registros filhos na tabela 'nutriente' devido à Foreign Key
            $stmtNut = $pdo->prepare("DELETE FROM nutriente WHERE refeicao_id = ?");
            $stmtNut->execute([$refeicao_id]);

            // 2. Exclui a refeição principal garantindo o ID e a segurança do usuário logado
            $stmtRef = $pdo->prepare("DELETE FROM refeicao WHERE id = ? AND usuario_id = ?");
            $stmtRef->execute([$refeicao_id, $usuario_id]);

            $pdo->commit();
            header("Location: ../views/dashboard.php?sucesso=excluido");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Erro crítico ao excluir o registro: " . $e->getMessage());
        }
    }

    // Validação básica de entradas para Salvar / Editar
    $descricao_texto = isset($_POST['descricao_comida']) ? trim($_POST['descricao_comida']) : ''; 
    if (empty($descricao_texto) || empty($data_consumo)) {
        header("Location: ../views/dashboard.php?erro=campos_vazios");
        exit;
    }

    // ==========================================
    // PASSO 1: RECALCULAR COM A IA SE FOR ATUALIZAÇÃO/INCLUSÃO
    // ==========================================
    $macrosEstimados = analisarRefeicaoComIA($descricao_texto);

    if ($macrosEstimados && isset($macrosEstimados['kcal'])) {
        $kcal     = intval($macrosEstimados['kcal']);
        $carbo    = floatval($macrosEstimados['carbo']);
        $proteina = floatval($macrosEstimados['proteina']);
        $gordura  = floatval($macrosEstimados['gordura']);
    } else {
        $kcal = 0; $carbo = 0; $proteina = 0; $gordura = 0;
    }

    // ==========================================
    // PASSO 2: SALVAR OU EDITAR NAS TABELAS
    // ==========================================
    try {
        $pdo->beginTransaction();

        if ($acao === 'editar' && !empty($refeicao_id)) {
            // OPERAÇÃO DE ATUALIZAÇÃO (UPDATE)
            $sqlRefeicao = "UPDATE refeicao SET tipo_refeicao = ?, descricao_texto = ? WHERE id = ? AND usuario_id = ?";
            $stmtRef = $pdo->prepare($sqlRefeicao);
            $stmtRef->execute([$tipo_refeicao, $descricao_texto, $refeicao_id, $usuario_id]);

            $sqlNutrientes = "UPDATE nutriente SET kcal = ?, carboidratos = ?, proteinas = ?, gorduras = ? WHERE refeicao_id = ?";
            $stmtNut = $pdo->prepare($sqlNutrientes);
            $stmtNut->execute([$kcal, $carbo, $proteina, $gordura, $refeicao_id]);

        } else {
            // OPERAÇÃO DE INCLUSÃO PADRÃO (INSERT)
            $sqlRefeicao = "INSERT INTO refeicao (usuario_id, data_consumo, tipo_refeicao, descricao_texto) VALUES (?, ?, ?, ?)";
            $stmtRef = $pdo->prepare($sqlRefeicao);
            $stmtRef->execute([$usuario_id, $data_consumo, $tipo_refeicao, $descricao_texto]);

            $refeicao_id = $pdo->lastInsertId();

            $sqlNutrientes = "INSERT INTO nutriente (refeicao_id, kcal, carboidratos, proteinas, gorduras) VALUES (?, ?, ?, ?, ?)";
            $stmtNut = $pdo->prepare($sqlNutrientes);
            $stmtNut->execute([$refeicao_id, $kcal, $carbo, $proteina, $gordura]);
        }

        $pdo->commit();
        header("Location: ../views/dashboard.php?sucesso=1");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erro crítico ao processar transação no banco: " . $e->getMessage());
    }
} else {
    header("Location: ../views/dashboard.php");
    exit;
}