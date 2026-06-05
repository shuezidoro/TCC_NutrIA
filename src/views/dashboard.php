<?php
session_start();

// Trava de segurança: Se o usuário não estiver logado, expulsa para a tela de login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../config/conexao.php';

$usuario_id = $_SESSION['usuario_id'];
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';

// ===================================================
// BUSCA AS REFEIÇÕES DO BANCO PARA EXIBIR NO CALENDÁRIO
// ===================================================
$eventos_refeicoes = [];
try {
    // Adicionado o campo descricao_texto na busca para carregar dinamicamente no modal de edição
    $stmt = $pdo->prepare("SELECT id, tipo_refeicao, data_consumo, descricao_texto FROM refeicao WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $refeicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($refeicoes as $ref) {
        $cor = '#00A3E0'; // Azul padrão
        if ($ref['tipo_refeicao'] == 'Almoço') $cor = '#10ac84';
        if ($ref['tipo_refeicao'] == 'Jantar') $cor = '#ee5253';
        if ($ref['tipo_refeicao'] == 'Café da Manhã') $cor = '#ff9f43';

        $eventos_refeicoes[] = [
            'id' => $ref['id'],
            'title' => $ref['tipo_refeicao'],
            'start' => $ref['data_consumo'],
            'backgroundColor' => $cor,
            'borderColor' => $cor,
            // Armazena propriedades estendidas no FullCalendar para resgatar via JS no clique
            'extendedProps' => [
                'descricao' => $ref['descricao_texto']
            ]
        ];
    }
} catch (Exception $e) {
    $eventos_refeicoes = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Nutricional</title>
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.11/locales/pt-br.global.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f6fa;
            margin: 0;
            padding: 0;
        }

        .main-header {
            background-color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* LAYOUT EM DUAS COLUNAS */
        .dashboard-grid {
            display: flex;
            gap: 25px;
            margin-top: 20px;
            align-items: flex-start;
        }

        #calendar {
            flex: 2;
            background: white;
            padding: 20px;
            border-radius: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        /* PAINEL LATERAL DE MACROS */
        .side-panel {
            flex: 1;
            background: white;
            padding: 25px;
            border-radius: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            position: sticky;
            top: 20px;
        }

        .macro-card {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 20px;
        }

        .macro-item {
            padding: 12px;
            border-radius: 10px;
            text-align: center;
            color: white;
        }

        .bg-prot { background: #ee5253; }
        .bg-carbo { background: #10ac84; }
        .bg-gord { background: #ff9f43; }
        .bg-kcal { background: #00A3E0; grid-column: span 2; }

        .extra-nutrients {
            margin-top: 20px;
            font-size: 0.95rem;
            color: #555;
            border-top: 1px solid #eee;
            padding-top: 15px;
            display: flex;
            justify-content: space-between;
        }

        /* ESTILIZAÇÃO DO MODAL */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 999;
        }

        .modal-content {
            background: white;
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
        }
    </style>
</head>
<body>

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

<div class="container">
    <div class="dashboard-grid">
        <div id='calendar'></div>

        <div class="side-panel">
            <h3 style="margin-top:0; color:#2c3e50;">Resumo Nutricional</h3>
            <p id="label-data-selecionada" style="color:#7f8c8d; font-weight:bold; margin-bottom: 15px;"></p>
            
            <div style="max-width: 200px; margin: 0 auto 15px auto;">
                <canvas id="macroChart"></canvas>
            </div>

            <div class="macro-card">
                <div class="macro-item bg-kcal">
                    <small>Energia Diária</small><br><strong id="res-kcal">0</strong> <small>kcal</small>
                </div>
                <div class="macro-item bg-prot">
                    <small>Proteínas</small><br><strong id="res-prot">0</strong><small>g</small>
                </div>
                <div class="macro-item bg-carbo">
                    <small>Carboidratos</small><br><strong id="res-carbo">0</strong><small>g</small>
                </div>
                <div class="macro-item bg-gord">
                    <small>Gorduras</small><br><strong id="res-gord">0</strong><small>g</small>
                </div>
                <div class="macro-item bg-gord">
                    <small>fibras</small><br><strong id="res-fibra" style="color:#27ae60;">0</strong><small>g</small>
                </div>
                <div class="macro-item bg-gord">
                    <small>Açúcares</small><br><strong id="res-acucar" style="color:#d35400;">0</strong><small>g</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="modalRefeicao" class="modal-overlay">
    <div class="modal-content">
        <h3 id="modalTitle" style="margin-top:0;">Adicionar Refeição (<span id="txt_data_visual"></span>)</h3>
        
        <form id="formRefeicao" action="../controllers/refeicao_controller.php" method="POST">
            <input type="hidden" name="data_refeicao" id="form_data">
            <input type="hidden" name="refeicao_id" id="refeicao_id" value="">
            <input type="hidden" name="acao" id="form_acao" value="salvar">

            <div class="form-group">
                <label for="tipo_refeicao">Tipo de Refeição:</label>
                <select name="tipo_refeicao" id="tipo_refeicao">
                    <option value="Café da Manhã">Café da Manhã</option>
                    <option value="Almoço">Almoço</option>
                    <option value="Café da Tarde">Café da Tarde</option>
                    <option value="Jantar">Jantar</option>
                </select>
            </div>

            <div class="form-group">
                <label for="descricao_comida">O que você comeu?</label>
                <textarea name="descricao_comida" id="descricao_comida" rows="4" placeholder="Ex: 2 ovos mexidos, 1 fatia de pão integral e um café preto..." required></textarea>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; align-items: center;">
                <button type="button" id="btnExcluirModal" style="padding:10px 15px; border:none; background:#ee5253; color:white; border-radius:6px; cursor:pointer; font-weight:bold; display:none; margin-right:auto;">Excluir</button>
                
                <button type="button" id="btnFecharModal" style="padding:10px 15px; border:none; background:#eee; border-radius:6px; cursor:pointer;">Cancelar</button>
                <button type="submit" id="btnSubmitModal" style="padding:10px 15px; border:none; background:#10ac84; color:white; border-radius:6px; cursor:pointer; font-weight:bold;">Salvar e Calcular</button>
            </div>
        </form>
    </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var modal = document.getElementById('modalRefeicao');
    var modalTitle = document.getElementById('modalTitle');
    var btnFechar = document.getElementById('btnFecharModal');
    var btnExcluir = document.getElementById('btnExcluirModal');
    var btnSubmit = document.getElementById('btnSubmitModal');
    var inputData = document.getElementById('form_data');
    var inputId = document.getElementById('refeicao_id');
    var inputAcao = document.getElementById('form_acao');
    var formRefeicao = document.getElementById('formRefeicao');
    var txtDataVisual = document.getElementById('txt_data_visual');
    
    var macroCtx = document.getElementById('macroChart').getContext('2d');
    var macroChart;

    function atualizarGrafico(prot, carbo, gord) {
        if (macroChart) macroChart.destroy();
        
        const total = prot + carbo + gord;
        const dadosGrafico = total > 0 ? [prot, carbo, gord] : [1];
        const coresGrafico = total > 0 ? ['#ee5253', '#10ac84', '#ff9f43'] : ['#f0f0f0'];

        macroChart = new Chart(macroCtx, {
            type: 'doughnut',
            data: {
                labels: ['Proteínas', 'Carboidratos', 'Gorduras'],
                datasets: [{
                    data: dadosGrafico,
                    backgroundColor: coresGrafico,
                    borderWidth: 0
                }]
            },
            options: {
                cutout: '75%',
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: total > 0 }
                }
            }
        });
    }

    function carregarMacrosDoDia(dataStr) {
        var dataFormatada = dataStr.split('-').reverse().join('/');
        document.getElementById('label-data-selecionada').innerText = dataFormatada;

        fetch(`../controllers/get_macros_controller.php?data=${dataStr}`)
            .then(response => response.json())
            .then(dados => {
                if(dados.erro) {
                    console.error(dados.erro);
                    return;
                }
                document.getElementById('res-kcal').innerText   = dados.kcal;
                document.getElementById('res-prot').innerText   = dados.prot;
                document.getElementById('res-carbo').innerText  = dados.carbo;
                document.getElementById('res-gord').innerText   = dados.gord;
                document.getElementById('res-fibra').innerText  = dados.fibra;
                document.getElementById('res-acucar').innerText = dados.acucar;

                atualizarGrafico(dados.prot, dados.carbo, dados.gord);
            });
    }

    // Validador auxiliar para impedir interações em dias futuros
    function verificarDataFutura(dataStr) {
        const hoje = new Date();
        hoje.setHours(0,0,0,0);
        
        // Corrige problemas de fusos horários locais ao ler strings YYYY-MM-DD
        const partesData = dataStr.split('-');
        const dataSelecionada = new Date(partesData[0], partesData[1] - 1, partesData[2]);
        
        if (dataSelecionada > hoje) {
            alert("⚠️ Não é permitido gerenciar ou adicionar refeições em datas futuras!");
            return true;
        }
        return false;
    }

    var calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      locale: 'pt-br',
      fixedWeekCount: false, 
      headerToolbar: {
        left: '',
        center: 'prev title next',
        right: ''
      },
      buttonText: { prev: '<', next: '>' },
      selectable: true,
      events: <?php echo json_encode($eventos_refeicoes); ?>,
      
      // Clique em um dia vazio do Calendário
      dateClick: function(info) {
        // Trava de segurança para data futura
        if (verificarDataFutura(info.dateStr)) return;

        carregarMacrosDoDia(info.dateStr);

        // Configura o modal em modo de inserção ("salvar")
        inputAcao.value = "salvar";
        inputId.value = "";
        inputData.value = info.dateStr;
        
        var dataFormatada = info.dateStr.split('-').reverse().join('/');
        txtDataVisual.innerText = dataFormatada;
        modalTitle.innerHTML = `Adicionar Refeição (${dataFormatada})`;
        
        document.getElementById('descricao_comida').value = '';
        document.getElementById('tipo_refeicao').value = 'Café da Manhã';
        btnExcluir.style.display = 'none';
        btnSubmit.innerText = "Salvar e Calcular";

        modal.style.display = 'flex';
      },

      // Clique em um evento (refeição) já cadastrado no calendário
      eventClick: function(info) {
        const evento = info.event;
        const dataStr = evento.startStr.split('T')[0];

        // Trava preventiva de segurança para data futura
        if (verificarDataFutura(dataStr)) return;

        carregarMacrosDoDia(dataStr);

        // Configura o modal em modo de alteração ("editar")
        inputAcao.value = "editar";
        inputId.value = evento.id;
        inputData.value = dataStr;

        var dataFormatada = dataStr.split('-').reverse().join('/');
        txtDataVisual.innerText = dataFormatada;
        modalTitle.innerHTML = `Editar Refeição (${dataFormatada})`;

        // Preenche o formulário com as informações salvas no banco
        document.getElementById('tipo_refeicao').value = evento.title;
        document.getElementById('descricao_comida').value = evento.extendedProps.descricao || '';
        
        btnExcluir.style.display = 'inline-block';
        btnSubmit.innerText = "Atualizar e Calcular";

        modal.style.display = 'flex';
      }
    });

    calendar.render();

    carregarMacrosDoDia(new Date().toISOString().split('T')[0]);

    // Gatilho do botão Excluir interno do Modal
    btnExcluir.addEventListener('click', function() {
        if (confirm("Tem certeza que deseja excluir permanentemente esta refeição?")) {
            inputAcao.value = "excluir";
            formRefeicao.submit();
        }
    });

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('erro') === 'nao_comivel') {
        alert("⚠️ Não foi possível calcular os nutrientes. Por favor, digite um alimento ou refeição válida!");
        window.history.replaceState({}, document.title, window.location.pathname);
    } else if (urlParams.get('erro') === 'campos_vazios') {
        alert("⚠️ Por favor, preencha todos os campos do formulário.");
        window.history.replaceState({}, document.title, window.location.pathname);
    } else if (urlParams.get('erro') === 'data_futura') {
        alert("⚠️ Operação negada! Não é permitido realizar registros em datas futuras.");
        window.history.replaceState({}, document.title, window.location.pathname);
    } else if (urlParams.get('sucesso') === '1') {
        alert("✅ Refeição registrada e calculada com sucesso!");
        window.history.replaceState({}, document.title, window.location.pathname);
    } else if (urlParams.get('sucesso') === 'excluido') {
        alert("🗑️ Refeição excluída com sucesso!");
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    btnFechar.addEventListener('click', function() {
        modal.style.display = 'none';
    });

    window.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
  });
</script>
</body>
</html>