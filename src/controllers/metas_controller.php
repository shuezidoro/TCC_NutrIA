<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_SESSION['usuario_id'] ?? null;

    // Trava de segurança: impede acessos inválidos
    if (!$usuario_id) {
        header("Location: ../views/login.php");
        exit;
    }

    // Captura o tipo enviado pelo formulário
    $tipo_meta_enviado = $_POST['tipo_meta'] ?? 'automatica';

    // TRATAMENTO DA DIVERGÊNCIA DO ENUM: Mapeia o valor recebido para os aceitos pelo banco ('Sistema' ou 'Manual')
    if ($tipo_meta_enviado === 'automatica' || $tipo_meta_enviado === 'Sistema') {
        $origem_meta = 'Sistema';
    } else {
        $origem_meta = 'Manual';
    }

    // Coleta os valores numéricos garantindo a integridade dos dados (intval previne vazios)
    $kcal_meta     = intval($_POST['calorias'] ?? 0);
    $proteina_meta = intval($_POST['proteina'] ?? 0);
    $carbo_meta    = intval($_POST['carbo'] ?? 0);
    $gordura_meta  = intval($_POST['gordura'] ?? 0);

    try {
        // Insere ou atualiza os registros de forma limpa na tabela metas_diarias
        $sql = "INSERT INTO metas_diarias 
                (usuario_id, kcal_meta, proteina_meta, carbo_meta, gordura_meta, origem_meta) 
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                kcal_meta = ?, 
                proteina_meta = ?, 
                carbo_meta = ?, 
                gordura_meta = ?, 
                origem_meta = ?,
                data_atualizacao = CURRENT_TIMESTAMP";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $usuario_id, $kcal_meta, $proteina_meta, $carbo_meta, $gordura_meta, $origem_meta, // Bind do INSERT
            $kcal_meta, $proteina_meta, $carbo_meta, $gordura_meta, $origem_meta              // Bind do UPDATE
        ]);

        // Redireciona com sucesso de volta para o Dashboard
        header("Location: ../views/dashboard.php?sucesso=meta_salva");
        exit;

    } catch (Exception $e) {
        die("Erro crítico ao salvar a meta completa no banco de dados: " . $e->getMessage());
    }
} else {
    header("Location: ../../index.php");
    exit;
}