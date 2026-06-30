<?php
// views/confirmar.php
require_once __DIR__ . '/../config/conexao.php';

$token = $_GET['token'] ?? '';

if (!empty($token)) {
    // 1. Busca se existe um usuário com esse token de ativação e que ainda não esteja ativo
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE token_ativacao = ? AND ativo = 0");
    $stmt->execute([$token]);
    $usuario = $stmt->fetch();

    if ($usuario) {
        // 2. Ativa o usuário e remove o token para segurança
        $stmtUpdate = $pdo->prepare("UPDATE usuarios SET ativo = 1, token_ativacao = NULL WHERE id = ?");
        $stmtUpdate->execute([$usuario['id']]);

        echo "<div style='text-align: center; margin-top: 50px; font-family: Arial, sans-serif;'>
                <h3 style='color: #28a745;'>Conta Ativada com Sucesso!</h3>
                <p>Seu e-mail foi validado. Agora você já pode entrar na sua conta.</p>
                <p><a href='login.php' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Ir para o Login</a></p>
              </div>";
    } else {
        echo "<div style='text-align: center; margin-top: 50px; font-family: Arial, sans-serif;'>
                <h3 style='color: #dc3545;'>Token Inválido ou Expirado</h3>
                <p>Este link de confirmação não é mais válido ou a conta já foi ativada anteriormente.</p>
                <p><a href='login.php'>Ir para a página de Login</a></p>
              </div>";
    }
} else {
    echo "Acesso inválido ou parâmetro ausente.";
}