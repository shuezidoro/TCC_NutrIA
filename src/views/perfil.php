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
    
        $stmtBio = $pdo->prepare("SELECT idade, peso, altura, nivel_atividade, objetivo, genero FROM biometria WHERE usuario_id = ?");
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
    <link rel="stylesheet" href="../../public/css/perfil_style.css">
    <title>Meu perfil - NutrIA</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

    <button id="btnEditarPerfil" style="padding: 10px 20px; background-color: #00A3E0; color: white; border: none; border-radius: 5px; cursor: pointer;">
    Editar Perfil
</button>

<div id="modalEditar" style="display: none; position: fixed; z-index: 999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center;">
    <div style="background-color: white; padding: 25px; border-radius: 8px; width: 90%; max-width: 450px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <h3 style="margin-top: 0;">Atualizar Meus Dados</h3>
        
        <form action="../controllers/perfil_controller.php" method="POST">
            <label>Nome:</label><br>
            <input type="text" name="nome" value="<?php echo htmlspecialchars($usuario_nome); ?>" required style="width: 100%; margin-bottom: 15px; padding: 8px;"><br>

            <label>Idade:</label><br>
            <input type="number" name="idade" value="<?php echo $biometria['idade'] ?? ''; ?>" required style="width: 100%; margin-bottom: 15px; padding: 8px;"><br>

            <label>Peso Atual (kg):</label><br>
            <input type="number" name="peso" step="0.1" value="<?php echo $biometria['peso'] ?? ''; ?>" required style="width: 100%; margin-bottom: 15px; padding: 8px;"><br>

            <label>Altura (cm):</label><br>
            <input type="number" name="altura" value="<?php echo isset($biometria['altura']) ? ($biometria['altura'] * 100) : ''; ?>" required style="width: 100%; margin-bottom: 15px; padding: 8px;"><br>

            <label>Gênero:</label><br>
            <select name="genero" required style="width: 100%; margin-bottom: 15px; padding: 8px;">
                <option value="Masculino" <?php echo ($biometria['genero'] ?? '') === 'Masculino' ? 'selected' : ''; ?>>Masculino</option>
                <option value="Feminino" <?php echo ($biometria['genero'] ?? '') === 'Feminino' ? 'selected' : ''; ?>>Feminino</option>
            </select><br>

            <label>Nível de Atividade:</label><br>
            <select name="nivel_atividade" required style="width: 100%; margin-bottom: 15px; padding: 8px;">
                <option value="Sedentario" <?php echo ($biometria['nivel_atividade'] ?? '') === 'Sedentario' ? 'selected' : ''; ?>>Sedentário</option>
                <option value="Leve" <?php echo ($biometria['nivel_atividade'] ?? '') === 'Leve' ? 'selected' : ''; ?>>Leve</option>
                <option value="Moderado" <?php echo ($biometria['nivel_atividade'] ?? '') === 'Moderado' ? 'selected' : ''; ?>>Moderado</option>
                <option value="Ativo" <?php echo ($biometria['nivel_atividade'] ?? '') === 'Ativo' ? 'selected' : ''; ?>>Ativo</option>
                <option value="Muito_Ativo" <?php echo ($biometria['nivel_atividade'] ?? '') === 'Muito_Ativo' ? 'selected' : ''; ?>>Muito Ativo</option>
            </select><br>

            <label>Objetivo:</label><br>
            <select name="objetivo" required style="width: 100%; margin-bottom: 20px; padding: 8px;">
                <option value="Perda_Peso" <?php echo ($biometria['objetivo'] ?? '') === 'Perda_Peso' ? 'selected' : ''; ?>>Perder Peso</option>
                <option value="Manter_Saude" <?php echo ($biometria['objetivo'] ?? '') === 'Manter_Saude' ? 'selected' : ''; ?>>Manter Saúde</option>
                <option value="Ganho_Massa" <?php echo ($biometria['objetivo'] ?? '') === 'Ganho_Massa' ? 'selected' : ''; ?>>Ganhar Massa</option>
            </select><br>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" id="btnFecharModal" style="padding: 8px 15px; background: #94a3b8; color: white; border: none; border-radius: 4px; cursor: pointer;">Cancelar</button>
                <button type="submit" style="padding: 8px 15px; background: #22c55e; color: white; border: none; border-radius: 4px; cursor: pointer;">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('modalEditar');
    const btnEditar = document.getElementById('btnEditarPerfil');
    const btnFechar = document.getElementById('btnFecharModal');

    btnEditar.addEventListener('click', () => modal.style.display = 'flex');
    btnFechar.addEventListener('click', () => modal.style.display = 'none');
    
    // Alerta opcional de sucesso vindo da URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('sucesso') === 'perfil_atualizado') {
        alert("✅ Perfil e histórico de peso atualizados com sucesso!");
        window.history.replaceState({}, document.title, window.location.pathname);
    }
</script>

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