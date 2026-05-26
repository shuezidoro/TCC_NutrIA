<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NutrIA</title>
</head>
<body>

    <h1>Acessar o Sistema</h1>

    <form action="../controllers/auth_controller.php" method="POST" autocomplete="off">
        
        <input type="hidden" name="acao" value="login">

        <label for="iemail">E-mail:</label>
        <input type="email" id="iemail" name="email" required><br><br>

        <label for="isenha">Senha:</label>
        <input type="password" id="isenha" name="senha" required><br><br>

        <input type="submit" value="Entrar">
    </form>

    <p>Não tem uma conta? <a href="cadastro.php">Cadastre-se aqui</a></p>

</body>
</html>