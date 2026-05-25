<?php
// Configurações do banco de dados
$servidor = "localhost";
$usuario  = "root";
$senha    = "";
$banco    = "nutria";

try {
    // Criação da conexão usando o padrão PDO (compatível com o controlador)
    $pdo = new PDO("mysql:host=$servidor;dbname=$banco;charset=utf8mb4", $usuario, $senha);
    
    // Configura o PDO para lançar exceções em caso de erros no banco
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (PDOException $e) {
    // Caso a conexão falhe, interrompe o sistema e exibe o erro
    die("Conexão falhou: " . $e->getMessage());
}
?>