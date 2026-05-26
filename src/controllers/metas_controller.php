<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_SESSION['usuario_id'] ?? null;
    $tipo_meta = $_POST['tipo_meta'] ?? '';

    // Trava de segurança: se não houver usuário logado, chuta para o login
    if (!$usuario_id) {
        header("Location: ../views/login.php");
        exit;
    }

    // Coleta o valor correto de calorias baseado na escolha do usuário
    if ($tipo_meta === 'automatica') {
        $calorias = intval($_POST['calorias_calculadas']);
    } else {
        $calorias = intval($_POST['calorias_manuais']);
    }

    try {
        // Insere a meta vinculada ao usuário. Se ele já tiver uma, atualiza (ON DUPLICATE KEY)
        // Usando o nome correto da sua coluna: kcal_meta
        $stmt = $pdo->prepare("INSERT INTO metas_diarias (usuario_id, kcal_meta) VALUES (?, ?) 
                               ON DUPLICATE KEY UPDATE kcal_meta = ?");
        $stmt->execute([$usuario_id, $calorias, $calorias]);

        // Redireciona o usuário direto para o Dashboard após salvar com sucesso
        header("Location: ../views/dashboard.php");
        exit;

    } catch (Exception $e) {
        // Exibe o erro técnico caso algo falhe (importante para os testes do TCC)
        die("Erro crítico ao salvar a meta no banco de dados: " . $e->getMessage());
    }
} else {
    // Se tentarem acessar o controlador direto pela URL, manda para a raiz
    header("Location: ../../index.php");
    exit;
}