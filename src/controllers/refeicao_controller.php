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
            $stmtNut= $pdo->prepare("DELETE FROM nutriente WHERE refeicao_id = ?");
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
    // PASSO 1: RECALCULAR COM A IA E VALIDAR SE É COMESTÍVEL
    // ==========================================
    $macrosEstimados = analisarRefeicaoComIA($descricao_texto);

    // TRAVA DE VALIDAÇÃO CRUCIAL: Se a IA retornar erro ou não for comestível, barra o fluxo aqui!
    if (!$macrosEstimados || isset($macrosEstimados['erro']) || !isset($macrosEstimados['kcal'])) {
        header("Location: ../views/dashboard.php?erro=nao_comivel");
        exit;
    }

    // Se passou na validação, coleta os nutrientes mapeados corretamente com o banco
   // Por volta da linha 64, adicione a variável do sódio coletando do array da IA:
    $kcal     = intval($macrosEstimados['kcal']);
    $carbo    = floatval($macrosEstimados['carbo']);
    $proteina = floatval($macrosEstimados['proteina']);
    $gordura  = floatval($macrosEstimados['gordura']);
    $acucar   = floatval($macrosEstimados['acucar'] ?? 0.00);
    $fibra    = floatval($macrosEstimados['fibra'] ?? 0.00);
    $sodio    = floatval($macrosEstimados['sodio'] ?? 0.00); // <-- NOVA LINHA

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

            // Adicionado sodio_obtido no UPDATE
            $sqlNutrientes = "UPDATE nutriente SET calorias_obtidas = ?, carbo_obtido = ?, proteina_obtida = ?, gordura_obtida = ?, acucar_obtido = ?, fibra_obtida = ?, sodio_obtido = ? WHERE refeicao_id = ?";
            $stmtNut = $pdo->prepare($sqlNutrientes);
            $stmtNut->execute([$kcal, $carbo, $proteina, $gordura, $acucar, $fibra, $sodio, $refeicao_id]);

        } else {
            // OPERAÇÃO DE INCLUSÃO PADRÃO (INSERT)
            $sqlRefeicao = "INSERT INTO refeicao (usuario_id, data_consumo, tipo_refeicao, descricao_texto) VALUES (?, ?, ?, ?)";
            $stmtRef = $pdo->prepare($sqlRefeicao);
            $stmtRef->execute([$usuario_id, $data_consumo, $tipo_refeicao, $descricao_texto]);

            $refeicao_id = $pdo->lastInsertId();

            // Adicionado sodio_obtido no INSERT
            $sqlNutrientes = "INSERT INTO nutriente (refeicao_id, calorias_obtidas, proteina_obtida, carbo_obtido, gordura_obtida, acucar_obtido, fibra_obtida, sodio_obtido) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmtNut = $pdo->prepare($sqlNutrientes);
            $stmtNut->execute([$refeicao_id, $kcal, $proteina, $carbo, $gordura, $acucar, $fibra, $sodio]);
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