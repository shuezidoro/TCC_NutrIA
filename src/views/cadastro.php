<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastre-se</title>
</head>
<body>

    <h1>Cadastre-se</h1>

    <form action="../controllers/auth_controller.php" method="POST">
        
        <input type="hidden" name="acao" value="cadastro">

        <label for="inome">Seu nome:</label>
        <input type="text" id="inome" name="nome" required><br>

        <label for="iemail">Seu e-mail:</label>
        <input type="email" id="iemail" name="email" required><br>

        <label for="isenha">Sua senha:</label>
        <input type="password" id="isenha" name="senha" required><br>

        <label for="iidade">Sua idade:</label>
        <input type="number" id="iidade" name="idade" required><br>

        <label for="ipeso">Seu peso (kg):</label>
        <input type="number" id="ipeso" name="peso" step="0.10" required><br>

        <label for="ialtura">Sua altura (cm):</label>
        <input type="number" id="ialtura" name="altura" step="1" required><br>

        <label for="isexo">Seu sexo:</label>
        <select id="isexo" name="sexo" required>
            <option value="Masculino">Masculino</option>
            <option value="Feminino">Feminino</option>
            <option value="Outro">Outro</option>
        </select><br>

        <label for="iatv">Atividade física:</label>
        <select id="iatv" name="atividade" required>
            <option value="Sedentario">Sedentário</option>
            <option value="Leve">Leve</option>
            <option value="Moderado">Moderado</option>
            <option value="Ativo">Ativo</option>
            <option value="Muito_Ativo">Muito Ativo</option>
        </select><br>

        <label for="iobjetivo">Objetivo:</label>
        <select id="iobjetivo" name="objetivo" required>
            <option value="Perda_Peso">Perder peso</option>
            <option value="Manter_Saude">Manter saúde</option>
            <option value="Ganho_Massa">Ganhar massa muscular</option>
        </select><br>

        <input type="submit" value="Cadastrar">
    </form>
    Acesso para <a href="login.php">Login</a> para quem já tem cadastro.
</body>
</html>