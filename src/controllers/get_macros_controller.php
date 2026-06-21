<?php
session_start();
header('Content-Type: application/json');

// Trava de segurança: impede acesso se não estiver logado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['erro' => 'Não autorizado']);
    exit;
}

require_once __DIR__ . '/../config/conexao.php';

$usuario_id = $_SESSION['usuario_id'];
// Se não passar data na URL, assume o dia de hoje
$data = $_GET['data'] ?? date('Y-m-d');

try {
    // Query corrigida: Mapeia as tabelas e faz a soma exata incluindo o sodio_obtido
    $sql = "SELECT 
                SUM(n.calorias_obtidas) as total_kcal, 
                SUM(n.proteina_obtida) as total_prot, 
                SUM(n.carbo_obtido) as total_carbo, 
                SUM(n.gordura_obtida) as total_gord,
                SUM(n.fibra_obtida) as total_fibra,
                SUM(n.acucar_obtido) as total_acucar,
                SUM(n.sodio_obtido) as total_sodio
            FROM refeicao r
            INNER JOIN nutriente n ON r.id = n.refeicao_id
            WHERE r.usuario_id = ? AND r.data_consumo = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id, $data]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    // Retorna os valores mapeados com as chaves exatas que o JavaScript do seu dashboard espera
    echo json_encode([
        'kcal'   => intval($resultado['total_kcal'] ?? 0),
        'prot'   => floatval($resultado['total_prot'] ?? 0.00),
        'carbo'  => floatval($resultado['total_carbo'] ?? 0.00),
        'gord'   => floatval($resultado['total_gord'] ?? 0.00),
        'fibra'  => floatval($resultado['total_fibra'] ?? 0.00),
        'acucar' => floatval($resultado['total_acucar'] ?? 0.00),
        'sodio'  => floatval($resultado['total_sodio'] ?? 0.00) // Chave capturada pelo seu document.getElementById('res-sodio')
    ]);

} catch (Exception $e) {
    // Retorno seguro em caso de erro na execução do banco
    echo json_encode([
        'kcal' => 0, 'prot' => 0, 'carbo' => 0, 'gord' => 0, 'fibra' => 0, 'acucar' => 0, 'sodio' => 0
    ]);
}