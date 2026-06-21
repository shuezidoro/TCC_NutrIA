<?php
session_start();

// Se o usuário não estiver logado, expulsa para a tela de login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../config/conexao.php'; 

$usuario_id = $_SESSION['usuario_id']; 
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário'; 

// ==========================================
// BUSCA OS DADOS BIOMÉTRICOS DO BANCO
// ==========================================
$idade = 'Não informado';
$peso = 'Não informado';
$altura = 'Não informado';
$atividade = 'Não informado';
$objetivo = 'Não informado';

try {
    // Busca a linha correspondente ao usuário na tabela biometria
    $stmtBio = $pdo->prepare("SELECT idade, peso, altura, nivel_atividade, objetivo FROM biometria WHERE usuario_id = ?");
    $stmtBio->execute([$usuario_id]);
    $biometria = $stmtBio->fetch(PDO::FETCH_ASSOC);

    if ($biometria) {
        $idade     = $biometria['idade'] . ' anos';
        $peso      = number_format($biometria['peso'], 1, ',', '.') . ' kg';
        
        // Se a altura foi salva em metros (ex: 1.75), exibe em cm multiplicando por 100
        $alturaRaw = floatval($biometria['altura']);
        if ($alturaRaw < 3) {
            $altura = ($alturaRaw * 100) . ' cm';
        } else {
            $altura = $alturaRaw . ' cm';
        }

        // Formatação amigável para o nível de atividade
        $procuraAtv = [
            'Sedentario' => 'Sedentário',
            'Leve' => 'Leve',
            'Moderado' => 'Moderado',
            'Ativo' => 'Ativo',
            'Muito_Ativo' => 'Muito Ativo'
        ];
        $atividade = $procuraAtv[$biometria['nivel_atividade']] ?? $biometria['nivel_atividade'];

        // Formatação amigável para o objetivo
        $procuraObj = [
            'Perda_Peso' => 'Perder peso',
            'Manter_Saude' => 'Manter saúde',
            'Ganho_Massa' => 'Ganhar massa muscular'
        ];
        $objetivo = $procuraObj[$biometria['objetivo']] ?? $biometria['objetivo'];
    }
} catch (Exception $e) {
    // Fallback silencioso caso ocorra algum erro na estrutura do banco
}

// ==========================================
// BUSCA HISTÓRICO DE PESO PARA O GRÁFICO
// ==========================================
try {
    $stmtPeso = $pdo->prepare("SELECT peso, data_registro FROM historico_peso WHERE usuario_id = ? ORDER BY data_registro ASC");
    $stmtPeso->execute([$usuario_id]);
    $historico = $stmtPeso->fetchAll(PDO::FETCH_ASSOC);

    $labelsDias = [];
    $dadosPesos = [];

    foreach ($historico as $registro) {
        $labelsDias[] = date('d/m', strtotime($registro['data_registro']));
        $dadosPesos[] = floatval($registro['peso']);
    }

} catch (Exception $e) {
    $labelsDias = [];
    $dadosPesos = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu perfil - NutrIA</title>
    

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
        }

        .main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #ffffff;
            padding: 15px 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .logo-area h2 {
            margin: 0;
            font-size: 1.6rem;
            color: #333;
        }
        .logo-area h2 span {
            color: #00A3E0;
        }

        .nav-links {
            display: flex;
            gap: 25px;
        }
        .nav-item {
            text-decoration: none;
            color: #64748b;
            font-weight: 600;
            font-size: 1rem;
            padding: 8px 16px;
            border-radius: 5px;
            transition: all 0.2s ease;
        }
        .nav-item:hover {
            color: #00A3E0;
            background-color: #f0f9ff;
        }
        .nav-item.active {
            color: #ffffff;
            background-color: #00A3E0;
        }

        .user-area {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .btn-logout {
            background-color: #e53e3e;
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 5px;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .perfil-layout {
            display: flex;
            gap: 30px;
            align-items: flex-start;
        }

        .info-card {
            flex: 1;
            background: #ffffff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .info-card h2 {
            margin-top: 0;
            font-size: 1.4rem;
            color: #2d3748;
            border-bottom: 2px solid #f0f4f8;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .info-card ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .info-card li {
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 1rem;
            display: flex;
            justify-content: space-between;
        }
        .info-card li:last-child {
            border-bottom: none;
        }

        .chart-card {
            flex: 2;
            background: #ffffff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .chart-card h3 {
            margin-top: 0;
            font-size: 1.4rem;
            color: #2d3748;
            border-bottom: 2px solid #f0f4f8;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .chart-container {
            position: relative;
            height: 320px;
            width: 100%;
        }

        @media (max-width: 850px) {
            .perfil-layout {
                flex-direction: column;
            }
            .info-card, .chart-card {
                width: 100%;
                flex: none;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <header class="main-header">
        <div class="logo-area">
            <h2>Nutr<span>IA</span></h2>
        </div>
        <nav class="nav-links">
            <a href="dashboard.php" class="nav-item">Dashboard</a>
            <a href="metas.php" class="nav-item">Minhas Metas</a>
            <a href="perfil.php" class="nav-item active">Meu Perfil</a>
        </nav>
        <div class="user-area">
            <span>Olá, <strong><?php echo htmlspecialchars($usuario_nome); ?></strong> 👋</span>
            <a href="../controllers/logout_controller.php" class="btn-logout">Sair</a>
        </div>
    </header>

    <div class="perfil-layout">
        
        <aside class="info-card">
            <h2>Informações Pessoais</h2>
            <ul>
                <li><span>Nome:</span> <strong><?php echo htmlspecialchars($usuario_nome); ?></strong></li>
                <li><span>Idade:</span> <strong><?php echo htmlspecialchars($idade); ?></strong></li>
                <li><span>Peso Atual:</span> <strong><?php echo htmlspecialchars($peso); ?></strong></li>
                <li><span>Altura:</span> <strong><?php echo htmlspecialchars($altura); ?></strong></li>
                <li><span>Atividade física:</span> <strong><?php echo htmlspecialchars($atividade); ?></strong></li>
                <li><span>Objetivo:</span> <strong><?php echo htmlspecialchars($objetivo); ?></strong></li>
            </ul>
        </aside>

        <section class="chart-card">
            <h3>Evolução do Peso Corporal</h3>
            <div class="chart-container">
                <canvas id="graficoEvolucaoPeso"></canvas>
            </div>
        </section>

    </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('graficoEvolucaoPeso').getContext('2d');
    
    var diasLabels = <?php echo json_encode($labelsDias); ?>;
    var pesosDados = <?php echo json_encode($dadosPesos); ?>;

    var grafico = new Chart(ctx, {
        type: 'line',
        data: {
            labels: diasLabels,
            datasets: [{
                label: 'Histórico de Peso (kg)',
                data: pesosDados,
                borderColor: '#00A3E0',
                backgroundColor: 'rgba(0, 163, 224, 0.08)',
                borderWidth: 3,
                tension: 0.3,
                pointBackgroundColor: '#00A3E0',
                pointBorderColor: '#ffffff',
                pointRadius: 5,
                pointHoverRadius: 8,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    grid: { color: '#f1f5f9' },
                    ticks: { color: '#64748b', font: { weight: 'bold' } }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#64748b', font: { weight: 'bold' } }
                }
            }
        }
    });
  });
</script>

</body>
</html>