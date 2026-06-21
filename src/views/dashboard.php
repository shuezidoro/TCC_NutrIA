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
    $stmt = $pdo->prepare("SELECT id, tipo_refeicao, data_consumo, descricao_texto FROM refeicao WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $refeicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($refeicoes as $ref) {
        $cor = '#00A3E0'; 
        if ($ref['tipo_refeicao'] == 'Almoço') $cor = '#10ac84'; 
        if ($ref['tipo_refeicao'] == 'Jantar') $cor = '#ee5253'; 
        if ($ref['tipo_refeicao'] == 'Café da Manhã') $cor = '#ff9f43'; 

        $eventos_refeicoes[] = [
            'id' => $ref['id'],
            'title' => $ref['tipo_refeicao'],
            'start' => $ref['data_consumo'],
            'description' => $ref['descricao_texto'], 
            'backgroundColor' => $cor,
            'borderColor' => $cor
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
        body { font-family: Arial, sans-serif; background-color: #f5f6fa; margin: 0; padding: 0; }
        .main-header { background-color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .nav-links a { text-decoration: none; margin: 0 10px; color: #555; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .dashboard-grid { display: flex; gap: 25px; margin-top: 20px; align-items: flex-start; }
        #calendar { flex: 2; background: white; padding: 20px; border-radius: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .side-panel { flex: 1; background: white; padding: 25px; border-radius: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); position: sticky; top: 20px; }
        
        /* REESTRUTURAÇÃO DO CARD PARA FORMATO DE LISTA VERTICAL */
        .macro-list { 
            display: flex; 
            flex-direction: column; 
            gap: 10px; 
            margin-top: 20px; 
        }
        
        .macro-item-list { 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            padding: 12px 16px; 
            border-radius: 8px; 
            color: white; 
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
        }
        
        /* NOVA CONFIGURAÇÃO DE CORES DA LISTA */
        .bg-kcal   { background-color: #2c3e50; margin-bottom: 5px; } /* Destaque para calorias */
        .bg-prot   { background-color: #ee5253; } /* Vermelho */
        .bg-carbo  { background-color: #10ac84; } /* Verde Escuro */
        .bg-gord   { background-color: #ff9f43; } /* Laranja */
        .bg-fibra  { background-color: #00b894; } /* MUDADO: Verde Menta Claro Distinto */
        .bg-acucar { background-color: #d35400; } /* Marrom */
        .bg-sodio  { background-color: #8e44ad; } /* Roxo */

        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); display: none; justify-content: center; align-items: center; z-index: 999; }
        .modal-content { background: white; padding: 25px; border-radius: 12px; width: 90%; max-width: 450px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
    </style>
</head>
<body>

<header class="main-header">
    <div class="logo-area"><h2>Nutr<span>IA</span></h2></div>
    <nav class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="metas.php">Minhas Metas</a>
        <a href="perfil.php">Meu Perfil</a>
    </nav>
    <div class="user-area">
        <span>Olá, <strong><?php echo htmlspecialchars($usuario_nome); ?></strong> 👋</span>
        <a href="../controllers/logout_controller.php" style="margin-left:15px; color:red; text-decoration:none;">Sair</a>
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

            <div class="macro-list">
                <div class="macro-item-list bg-kcal">
                    <span>Energia Diária</span>
                    <span><span id="res-kcal">0</span> kcal</span>
                </div>
                <div class="macro-item-list bg-prot">
                    <span>Proteínas</span>
                    <span><span id="res-prot">0</span> g</span>
                </div>
                <div class="macro-item-list bg-carbo">
                    <span>Carboidratos</span>
                    <span><span id="res-carbo">0</span> g</span>
                </div>
                <div class="macro-item-list bg-gord">
                    <span>Gorduras</span>
                    <span><span id="res-gord">0</span> g</span>
                </div>
                <div class="macro-item-list bg-fibra">
                    <span>Fibras</span>
                    <span><span id="res-fibra">0</span> g</span>
                </div>
                <div class="macro-item-list bg-acucar">
                    <span>Açúcares</span>
                    <span><span id="res-acucar">0</span> g</span>
                </div>
                <div class="macro-item-list bg-sodio">
                    <span>Sódio Total</span>
                    <span><span id="res-sodio">0</span> mg</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="modalRefeicao" class="modal-overlay">
    <div class="modal-content">
        <h3 id="modal_titulo" style="margin-top:0;">Adicionar Refeição</h3>
        
        <form action="../controllers/refeicao_controller.php" method="POST" id="formRefeicao">
            <input type="hidden" name="data_refeicao" id="form_data">
            <input type="hidden" name="refeicao_id" id="form_refeicao_id">
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
                <textarea name="descricao_comida" id="descricao_comida" rows="4" placeholder="Ex: 2 ovos mexidos, 1 fatia de pão integral..." required></textarea>
            </div>

            <div style="display: flex; gap: 10px; justify-content: space-between; align-items: center;">
                <button type="button" id="btnExcluirMeta" style="padding:10px 15px; border:none; background:#ee5253; color:white; border-radius:6px; cursor:pointer; display:none; font-weight:bold;">Excluir</button>
                
                <div style="display: flex; gap: 10px; margin-left: auto;">
                    <button type="button" id="btnFecharModal" style="padding:10px 15px; border:none; background:#eee; border-radius:6px; cursor:pointer;">Cancelar</button>
                    <button type="submit" id="btnEnviarModal" style="padding:10px 15px; border:none; background:#10ac84; color:white; border-radius:6px; cursor:pointer; font-weight:bold;">Salvar e Calcular</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var modal = document.getElementById('modalRefeicao');
    var btnFechar = document.getElementById('btnFecharModal');
    var btnExcluir = document.getElementById('btnExcluirMeta');
    
    var inputData = document.getElementById('form_data');
    var inputId = document.getElementById('form_refeicao_id');
    var inputAcao = document.getElementById('form_acao');
    var txtTitulo = document.getElementById('modal_titulo');
    var txtDescricao = document.getElementById('descricao_comida');
    var selectTipo = document.getElementById('tipo_refeicao');

    var macroCtx = document.getElementById('macroChart').getContext('2d');
    var macroChart;

    function atualizarGrafico(prot, carbo, gord, fibra, acucar, sodioMg) {
        if (macroChart) macroChart.destroy();

        const sodioG = sodioMg / 1000;
        const totalGramas = prot + carbo + gord + fibra + acucar + sodioG;

        let dadosGrafico, coresGrafico, labelsGrafico;

        if (totalGramas > 0) {
            dadosGrafico = [
                ((prot / totalGramas) * 100).toFixed(1),
                ((carbo / totalGramas) * 100).toFixed(1),
                ((gord / totalGramas) * 100).toFixed(1),
                ((fibra / totalGramas) * 100).toFixed(1),
                ((acucar / totalGramas) * 100).toFixed(1),
                ((sodioG / totalGramas) * 100).toFixed(1)
            ];
            // Atualizado com o novo verde menta claro para as Fibras (#00b894)
            coresGrafico = ['#ee5253', '#10ac84', '#ff9f43', '#00b894', '#d35400', '#8e44ad'];
            labelsGrafico = ['Proteínas', 'Carboidratos', 'Gorduras', 'Fibras', 'Açúcares', 'Sódio'];
        } else {
            dadosGrafico = [100];
            coresGrafico = ['#f0f0f0'];
            labelsGrafico = ['Sem registros'];
        }

        macroChart = new Chart(macroCtx, {
            type: 'doughnut',
            data: {
                labels: labelsGrafico,
                datasets: [{ 
                    data: dadosGrafico, 
                    backgroundColor: coresGrafico, 
                    borderWidth: 1,
                    borderColor: '#ffffff'
                }]
            },
            options: { 
                cutout: '70%', 
                plugins: { 
                    legend: { display: false }, 
                    tooltip: { 
                        enabled: true,
                        callbacks: {
                            label: function(context) {
                                if (totalGramas === 0) return ' Nenhuma refeição cadastrada';
                                return ` ${context.label}: ${context.raw}%`;
                            }
                        }
                    } 
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
                document.getElementById('res-kcal').innerText   = dados.kcal || 0;
                document.getElementById('res-prot').innerText   = dados.prot || 0;
                document.getElementById('res-carbo').innerText  = dados.carbo || 0;
                document.getElementById('res-gord').innerText   = dados.gord || 0;
                document.getElementById('res-fibra').innerText  = dados.fibra || 0;
                document.getElementById('res-acucar').innerText = dados.acucar || 0;
                document.getElementById('res-sodio').innerText  = dados.sodio || 0;
                
                atualizarGrafico(
                    parseFloat(dados.prot || 0), 
                    parseFloat(dados.carbo || 0), 
                    parseFloat(dados.gord || 0),
                    parseFloat(dados.fibra || 0),
                    parseFloat(dados.acucar || 0),
                    parseFloat(dados.sodio || 0)
                );
            });
    }

    var calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      locale: 'pt-br',
      fixedWeekCount: false,
      headerToolbar: { left: '', center: 'prev title next', right: '' },
      buttonText: { prev: '<', next: '>' },
      selectable: true,
      events: <?php echo json_encode($eventos_refeicoes); ?>,
      
      dateClick: function(info) {
        carregarMacrosDoDia(info.dateStr);

        inputData.value = info.dateStr;
        inputId.value = "";
        inputAcao.value = "salvar";
        txtTitulo.innerText = "Adicionar Refeição (" + info.dateStr.split('-').reverse().join('/') + ")";
        txtDescricao.value = "";
        btnExcluir.style.display = "none";

        modal.style.display = 'flex';
      },

      eventClick: function(info) {
        var evento = info.event;
        var dataStr = evento.startStr.split('T')[0];
        
        carregarMacrosDoDia(dataStr);

        inputData.value = dataStr;
        inputId.value = evento.id;
        inputAcao.value = "editar";
        txtTitulo.innerText = "Editar Refeição";
        txtDescricao.value = evento.extendedProps.description;
        selectTipo.value = evento.title;
        
        btnExcluir.style.display = "block"; 
        modal.style.display = 'flex';
      }
    });

    calendar.render();
    carregarMacrosDoDia(new Date().toISOString().split('T')[0]);

    btnExcluir.addEventListener('click', function() {
        if (confirm("Tem certeza que deseja remover esta refeição? Seus macros serão recalculados.")) {
            inputAcao.value = "excluir";
            document.getElementById('formRefeicao').submit();
        }
    });

    const urlParams = new URLSearchParams(window.location.search);
    
    if (urlParams.get('erro') === 'nao_comivel') {
        alert("⚠️ Não foi possível calcular os nutrientes. Por favor, digite um alimento ou refeição válida!");
        window.history.replaceState({}, document.title, window.location.pathname);
    } else if (urlParams.get('erro') === 'campos_vazios') {
        alert("⚠️ Por favor, preencha todos os campos antes de enviar.");
        window.history.replaceState({}, document.title, window.location.pathname);
    } else if (urlParams.get('sucesso') === 'excluido') {
        alert("✅ Refeição excluída e painel atualizado!");
        window.history.replaceState({}, document.title, window.location.pathname);
    } else if (urlParams.get('sucesso') === '1') {
        alert("✅ Refeição salva e calculada com sucesso!");
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    btnFechar.addEventListener('click', function() { modal.style.display = 'none'; });
    window.addEventListener('click', function(e) { if (e.target === modal) modal.style.display = 'none'; });
  });
</script>
</body>
</html>