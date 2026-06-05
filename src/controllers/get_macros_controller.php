<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['erro' => 'Não autorizado']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
// Se não passar data na URL, assume o dia de hoje
$data = $_GET['data'] ?? date('Y-m-d');

try {
    // Query que junta as tabelas e soma os nutrientes do dia do utilizador específico
    $sql = "SELECT 
                SUM(n.calorias_obtidas) as kcal, 
                SUM(n.proteina_obtida) as prot, 
                SUM(n.carbo_obtido) as carbo, 
                SUM(n.gordura_obtida) as gord,
                SUM(n.fibra_obtida) as fibra,
                SUM(n.acucar_obtido) as acucar
            FROM nutriente n
            JOIN refeicao r ON n.refeicao_id = r.id
            WHERE r.usuario_id = ? AND r.data_consumo = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id, $data]);
    $macros = $stmt->fetch(PDO::FETCH_ASSOC);

    // Retorna os valores somados ou 0 caso o dia esteja vazio
    echo json_encode([
        'kcal'   => (int)($macros['kcal'] ?? 0),
        'prot'   => (float)($macros['prot'] ?? 0),
        'carbo'  => (float)($macros['carbo'] ?? 0),
        'gord'   => (float)($macros['gord'] ?? 0),
        'fibra'  => (float)($macros['fibra'] ?? 0),
        'acucar' => (float)($macros['acucar'] ?? 0)
    ]);
} catch (Exception $e) {
    echo json_encode(['erro' => $e->getMessage()]);
}