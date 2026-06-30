<?php
session_start();

// Trava de segurança: Se o utilizador não estiver logado, expulsa para a página de login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../config/conexao.php';

$usuario_id = $_SESSION['usuario_id'];
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';

// ===================================================
// SISTEMA DE METAS: DIRETRIZES DIÁRIAS COM RECOVERY SAUDÁVEL (CORRIGIDO)
// ===================================================
$metas = [
    'kcal' => 2000,
    'prot' => 120,
    'carbo' => 250,
    'gord' => 65,
    'fibra' => 25,
    'acucar' => 50,
    'sodio' => 2400
];

try {
    // 1. Tenta obter as metas salvas corretamente da tabela 'metas_diarias'
$stmtMetas = $pdo->prepare("SELECT kcal_meta, proteina_meta, carbo_meta, gordura_meta FROM metas_diarias WHERE usuario_id = ? ORDER BY id DESC LIMIT 1");
    $stmtMetas->execute([$usuario_id]);
    $userMeta = $stmtMetas->fetch(PDO::FETCH_ASSOC);

    if ($userMeta) {
        $metas['kcal'] = !empty($userMeta['kcal_meta']) ? intval($userMeta['kcal_meta']) : 2000;
        $metas['prot'] = !empty($userMeta['proteina_meta']) ? floatval($userMeta['proteina_meta']) : 120;
        $metas['carbo'] = !empty($userMeta['carbo_meta']) ? floatval($userMeta['carbo_meta']) : 250;
        $metas['gord'] = !empty($userMeta['gordura_meta']) ? floatval($userMeta['gordura_meta']) : 65;
    } else {
        // 2. CASO NÃO EXISTA REGISTRO: calcula dinamicamente usando a mesma fórmula de Harris-Benedict de metas.php
        $stmtBio = $pdo->prepare("SELECT * FROM biometria WHERE usuario_id = ? LIMIT 1");
        $stmtBio->execute([$usuario_id]);
        $biometria = $stmtBio->fetch(PDO::FETCH_ASSOC);

        if ($biometria) {
            $peso = $biometria['peso'];
            $altura_cm = $biometria['altura'] * 100; // Converte metros para centímetros
            $idade = $biometria['idade'];
            $genero = $biometria['genero'];
            $nivel_atividade = $biometria['nivel_atividade'];
            $objetivo = $biometria['objetivo'];

            // Equação de Harris-Benedict Revisada
            if ($genero === 'Masculino') {
                $tmb = 88.36 + (13.4 * $peso) + (4.8 * $altura_cm) - (5.7 * $idade);
            } else {
                $tmb = 447.59 + (9.2 * $peso) + (3.1 * $altura_cm) - (4.3 * $idade);
            }

            // Fatores de atividade física idênticos ao metas.php
            $fatores = [
                'Sedentario'   => 1.2,
                'Leve'         => 1.375,
                'Moderado'     => 1.55,
                'Ativo'        => 1.725,
                'Muito_Ativo'  => 1.9
            ];
            $fator = $fatores[$nivel_atividade] ?? 1.2;
            $get = $tmb * $fator; 

            // Ajuste conforme o Objetivo
            $meta_calorica = $get;
            if ($objetivo === 'Perda_Peso') {
                $meta_calorica = $get - 500; 
            } elseif ($objetivo === 'Ganho_Massa') {
                $meta_calorica = $get + 400; 
            }

            $meta_final_auto = round($meta_calorica);

            // Define e distribui os macronutrientes na mesma proporção (40% Carbo, 30% Prot, 30% Gord)
            $metas['kcal']  = $meta_final_auto;
            $metas['carbo'] = round(($meta_final_auto * 0.40) / 4);
            $metas['prot']  = round(($meta_final_auto * 0.30) / 4);
            $metas['gord']  = round(($meta_final_auto * 0.30) / 9);
        }
    }
} catch (Exception $e) {
    // Caso ocorra qualquer erro crítico inesperado, mantém o fallback seguro com valores padrão
}

// ===================================================
// CALENDÁRIO: MAPEAMENTO E BUSCA DE REFEIÇÕES
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
    <link rel="stylesheet" href="../../public/css/dashboard_style.css">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.11/locales/pt-br.global.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
       
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
                    <div class="macro-info">
                        <span>Energia Diária</span>
                        <span><span id="res-kcal">0</span> / <?php echo $metas['kcal']; ?> kcal</span>
                    </div>
                    <div class="progress-bar-container">
                        <div id="bar-kcal" class="progress-bar-fill"></div>
                    </div>
                </div>

                <div class="macro-item-list bg-prot">
                    <div class="macro-info">
                        <span>Proteínas</span>
                        <span><span id="res-prot">0</span> / <?php echo $metas['prot']; ?> g</span>
                    </div>
                    <div class="progress-bar-container">
                        <div id="bar-prot" class="progress-bar-fill"></div>
                    </div>
                </div>

                <div class="macro-item-list bg-carbo">
                    <div class="macro-info">
                        <span>Carboidratos</span>
                        <span><span id="res-carbo">0</span> / <?php echo $metas['carbo']; ?> g</span>
                    </div>
                    <div class="progress-bar-container">
                        <div id="bar-carbo" class="progress-bar-fill"></div>
                    </div>
                </div>

                <div class="macro-item-list bg-gord">
                    <div class="macro-info">
                        <span>Gorduras</span>
                        <span><span id="res-gord">0</span> / <?php echo $metas['gord']; ?> g</span>
                    </div>
                    <div class="progress-bar-container">
                        <div id="bar-gord" class="progress-bar-fill"></div>
                    </div>
                </div>

                <div class="macro-item-list bg-fibra">
                    <div class="macro-info">
                        <span>Fibras</span>
                        <span><span id="res-fibra">0</span> / <?php echo $metas['fibra']; ?> g</span>
                    </div>
                    <div class="progress-bar-container">
                        <div id="bar-fibra" class="progress-bar-fill"></div>
                    </div>
                </div>

                <div class="macro-item-list bg-acucar">
                    <div class="macro-info">
                        <span>Açúcares</span>
                        <span><span id="res-acucar">0</span> / <?php echo $metas['acucar']; ?> g</span>
                    </div>
                    <div class="progress-bar-container">
                        <div id="bar-acucar" class="progress-bar-fill"></div>
                    </div>
                </div>

                <div class="macro-item-list bg-sodio">
                    <div class="macro-info">
                        <span>Sódio Total</span>
                        <span><span id="res-sodio">0</span> / <?php echo $metas['sodio']; ?> mg</span>
                    </div>
                    <div class="progress-bar-container">
                        <div id="bar-sodio" class="progress-bar-fill"></div>
                    </div>
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

    // Parser seguro das metas vindas do PHP
    const metasDiarias = <?php echo json_encode($metas); ?>;

    // Calcula as frações em tempo real e atualiza as larguras css dinamicamente
    function atualizarBarraProgresso(elementId, valorAtual, valorMeta) {
        const barra = document.getElementById(elementId);
        if (!barra) return;
        
        // Seleciona o card pai (macro-item-list) para aplicar efeitos visuais
        const cardPai = barra.closest('.macro-item-list');
        
        let percentual = 0;
        if (valorMeta > 0) {
            percentual = (valorAtual / valorMeta) * 100;
        }
        
        if (percentual > 100) {
            // META ULTRAPASSADA: Estímulo Visual de Alerta
            barra.style.width = '100%';
            barra.style.backgroundColor = '#ff2a2a'; // Amarelo/Laranja Neon chamativo
            
            if (cardPai) {
                cardPai.style.boxShadow = '0 0 15px rgba(255, 42, 42, 0.75)';
                cardPai.style.borderLeft = '6px solid #ff2a2a';
            }
        } else {
            // DENTRO DA META: Mantém o comportamento original limpo
            if (percentual < 0) percentual = 0;
            barra.style.width = percentual.toFixed(1) + '%';
            barra.style.backgroundColor = '#ffffff'; // Volta a ser branca
            
            if (cardPai) {
                cardPai.style.boxShadow = '0 2px 4px rgba(0,0,0,0.04)';
                cardPai.style.borderLeft = '4px solid transparent';
            }
        }
    }

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
            coresGrafico = ['#ee5253', '#0d8fa0', '#ff9f43', '#00b894', '#d35400', '#8e44ad'];
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
                const kcalVal   = dados.kcal || 0;
                const protVal   = dados.prot || 0;
                const carboVal  = dados.carbo || 0;
                const gordVal   = dados.gord || 0;
                const fibraVal  = dados.fibra || 0;
                const acucarVal = dados.acucar || 0;
                const sodioVal  = dados.sodio || 0;

                document.getElementById('res-kcal').innerText   = kcalVal;
                document.getElementById('res-prot').innerText   = protVal;
                document.getElementById('res-carbo').innerText  = carboVal;
                document.getElementById('res-gord').innerText   = gordVal;
                document.getElementById('res-fibra').innerText  = fibraVal;
                document.getElementById('res-acucar').innerText = acucarVal;
                document.getElementById('res-sodio').innerText  = sodioVal;
                
                // Dispara a animação fluida das barras
                atualizarBarraProgresso('bar-kcal', kcalVal, metasDiarias.kcal);
                atualizarBarraProgresso('bar-prot', protVal, metasDiarias.prot);
                atualizarBarraProgresso('bar-carbo', carboVal, metasDiarias.carbo);
                atualizarBarraProgresso('bar-gord', gordVal, metasDiarias.gord);
                atualizarBarraProgresso('bar-fibra', fibraVal, metasDiarias.fibra);
                atualizarBarraProgresso('bar-acucar', acucarVal, metasDiarias.acucar);
                atualizarBarraProgresso('bar-sodio', sodioVal, metasDiarias.sodio);

                atualizarGrafico(
                    parseFloat(protVal), 
                    parseFloat(carboVal), 
                    parseFloat(gordVal),
                    parseFloat(fibraVal),
                    parseFloat(acucarVal),
                    parseFloat(sodioVal)
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