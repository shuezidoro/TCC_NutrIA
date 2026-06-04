<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_SESSION['usuario_id'] ?? null;
    $origem_meta = $_POST['tipo_meta'] ?? 'automatica';

    // Trava de segurança
    if (!$usuario_id) {
        header("Location: ../views/login.php");
        exit;
    }

    // Coleta os valores unificados vindos de qualquer um dos formulários
    $kcal_meta     = intval($_POST['calorias']);
    $proteina_meta = intval($_POST['proteina']);
    $carbo_meta    = intval($_POST['carbo']);
    $gordura_meta  = intval($_POST['gordura']);

    try {
        // Insere a meta completa vinculada ao id do usuário. Se já existir, atualiza tudo.
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
            $usuario_id, $kcal_meta, $proteina_meta, $carbo_meta, $gordura_meta, $origem_meta, // Dados do INSERT
            $kcal_meta, $proteina_meta, $carbo_meta, $gordura_meta, $origem_meta              // Dados do UPDATE
        ]);

        // Redireciona para o Dashboard após salvar com sucesso
        header("Location: ../views/dashboard.php");
        exit;

    } catch (Exception $e) {
        die("Erro crítico ao salvar a meta completa no banco de dados: " . $e->getMessage());
    }
} else {
    header("Location: ../../index.php");
    exit;
}