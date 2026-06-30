<?php
session_start();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../public/css/login_style.css">
    <title>Login - NutrIA</title>
</head>
<body>

    <h1>Acessar o Sistema</h1>

    <?php if (isset($_GET['erro_login'])): ?>
        <p style="color: red; font-weight: bold;">⚠️ E-mail ou senha incorretos!</p>
    <?php endif; ?>

    <?php if (isset($_GET['senha_alterada'])): ?>
        <p style="color: green; font-weight: bold;">✅ Senha atualizada com sucesso! Faça login com a nova senha.</p>
    <?php endif; ?>

    <form action="../controllers/auth_controller.php" method="POST" autocomplete="off">
        
        <input type="hidden" name="acao" value="login">

        <label for="iemail">E-mail:</label>
        <input type="email" id="iemail" name="email" required><br><br>

        <label for="isenha">Senha:</label>
        <input type="password" id="isenha" name="senha" required><br><br>

        <input type="submit" value="Entrar">
    </form>

    <p><a href="esqueci_senha.php">Esqueci minha senha</a></p>
    <p>Não tem uma conta? <a href="cadastro.php">Cadastre-se aqui</a></p>

</body>
</html>