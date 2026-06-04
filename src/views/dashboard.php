<?php
session_start();

// Trava de segurança: Se o usuário não estiver logado, expulsa para a tela de login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../config/conexao.php';

$usuario_id = $_SESSION['usuario_id'];

// CORREÇÃO: Garante que a variável exista buscando da Sessão ou definindo um valor padrão
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.11/locales/pt-br.global.min.js'></script>
</head>
<style>
    /* ==========================================
   ESTILIZAÇÃO DO NOVO HEADER
   ========================================== */
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

/* Área do Logotipo */
.logo-area h2 {
    margin: 0;
    font-size: 1.6rem;
    color: #333;
}
.logo-area h2 span {
    color: #00A3E0; /* O azul característico da IA */
}

/* Links Centrais de Navegação */
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
/* Efeito Hover nos Menus */
.nav-item:hover {
    color: #00A3E0;
    background-color: #f0f9ff;
}
/* Item de página ativo (onde o usuário está no momento) */
.nav-item.active {
    color: #ffffff;
    background-color: #00A3E0;
}

/* Área da Direita (Usuário e Logout) */
.user-area {
    display: flex;
    align-items: center;
    gap: 20px;
}
.welcome-txt {
    font-size: 0.95rem;
    color: #4a5568;
}
.btn-logout {
    background-color: #e53e3e;
    color: white;
    text-decoration: none;
    padding: 8px 16px;
    border-radius: 5px;
    font-weight: bold;
    font-size: 0.9rem;
    transition: background 0.2s;
}
.btn-logout:hover {
    background-color: #c53030;
}

/* Ajuste fino para o container do calendário não colar nas bordas */
.container {
    max-width: 1100px;
    margin: 0 auto;
}
</style>
<body>
    <header class="main-header">
    <div class="logo-area">
        <h2>Nutr<span>IA</span></h2>
    </div>
    
    <nav class="nav-links">
        <a href="dashboard.php" class="nav-item active">Dashboard</a>
        <a href="metas.php" class="nav-item">Minhas Metas</a>
        <a href="perfil.php" class="nav-item">Meu Perfil</a>
    </nav>

    <div class="user-area">
        <span class="welcome-txt">Olá, <strong><?php echo htmlspecialchars($usuario_nome); ?></strong></span>
        <a href="../controllers/logout_controller.php" class="btn-logout">Sair</a>
    </div>
</header>
    <div id='calendar' style="max-width: 900px; margin: 40px auto; padding: 10px; background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);"></div>

<div id="modalRefeicao" style="display: none; position: fixed; z-index: 999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center;">
    <div style="background: white; padding: 25px; border-radius: 8px; width: 100%; max-width: 400px; box-shadow: 0 4px 20px rgba(0,0,0,0.2);">
        <h3 style="margin-top: 0; color: #00A3E0;">Registrar Refeição</h3>
        
        <form action="../controllers/refeicao_controller.php" method="POST">
            <input type="hidden" id="form_data" name="data_refeicao">

            <p>Data selecionada: <strong id="txt_data_visual"></strong></p>

            <div style="margin-bottom: 15px;">
                <label style="display:block; margin-bottom:5px; font-weight:bold;">Tipo da Refeição:</label>
                <select name="tipo_refeicao" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;" required>
                    <option value="Café da Manhã">Café da Manhã</option>
                    <option value="Almoço">Almoço</option>
                    <option value="Café da Tarde">Café da Tarde</option>
                    <option value="Jantar">Jantar</option>
                    <option value="Ceia">Ceia / Snack</option>
                </select>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display:block; margin-bottom:5px; font-weight:bold;">O que você comeu?</label>
                <textarea name="descricao_comida" rows="3" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; resize:vertical;" placeholder="Ex: 200g de arroz, 100g de feijão e 150g de frango grelhado" required></textarea>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" id="btnFecharModal" style="padding: 8px 15px; background: #ccc; border: none; border-radius: 4px; cursor: pointer;">Cancelar</button>
                <button type="submit" style="padding: 8px 15px; background: #00A3E0; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">Salvar</button>
            </div>
        </form>
    </div>
</div>
</body>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var modal = document.getElementById('modalRefeicao');
    var btnFechar = document.getElementById('btnFecharModal');
    var inputData = document.getElementById('form_data');
    var txtDataVisual = document.getElementById('txt_data_visual');

    var calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      locale: 'pt-br',
      
      // REMOVE A ROW EXTRA: Ajusta dinamicamente para não exibir semanas vazias
      fixedWeekCount: false, 

      // CUSTOMIZAÇÃO DO HEADER:
      headerToolbar: {
        left: '',          // Remove tudo do canto esquerdo
        center: 'prev title next', // Centraliza os botões junto com o nome do mês: < Maio de 2026 >
        right: ''          // Limpa completamente o canto direito (remove botão de mês/semana)
      },

      // Altera o texto padrão dos botões para os símbolos que você pediu
      buttonText: {
        prev: '<',
        next: '>'
      },
      
      selectable: true,
      events: [], // Calendário inicia totalmente limpo
      
      // AÇÃO AO CLICAR NO DIA: Abre o formulário correspondente
      dateClick: function(info) {
        // 1. Guarda a data no campo oculto (formato ISO: YYYY-MM-DD) para enviar ao banco
        inputData.value = info.dateStr;
        
        // 2. Formata a exibição amigável para o usuário (DD/MM/AAAA)
        var dataFormatada = info.dateStr.split('-').reverse().join('/');
        txtDataVisual.innerText = dataFormatada;

        // 3. Torna o Modal visível usando Flexbox
        modal.style.display = 'flex';
      }
    });

    calendar.render();

    // Ação para fechar o formulário caso o usuário desista
    btnFechar.addEventListener('click', function() {
        modal.style.display = 'none';
    });
  });
</script>
</html>