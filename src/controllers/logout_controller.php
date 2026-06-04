<?php
// Inicia a sessão para ter acesso aos dados armazenados do usuário
session_start();

// 1. Limpa todas as variáveis salvas na sessão ($_SESSION['usuario_id'], $_SESSION['usuario_nome'], etc.)
$_SESSION = array();

// 2. Destrói o cookie de sessão no navegador do usuário por completo (Boa prática de segurança para o TCC)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// 3. Destrói a sessão ativa no servidor
session_destroy();

// 4. Redireciona o usuário para a tela de login na pasta views
header("Location: ../views/login.php");
exit;