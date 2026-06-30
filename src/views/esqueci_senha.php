<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Recuperar Senha - NutrIA</title>
    <link rel="stylesheet" href="../../public/css/login_style.css">
</head>
<body>
    <h1>Recuperar Senha</h1>
    <form action="../controllers/auth_controller.php" method="POST">
        <input type="hidden" name="acao" value="solicitar_recuperacao">
        
        <label for="iemail">Digite seu e-mail cadastrado:</label>
        <input type="email" id="iemail" name="email" required><br><br>
        
        <input type="submit" value="Enviar Link de Recuperação">
    </form>
    <p><a href="login.php">Voltar para o Login</a></p>
</body>
</html>