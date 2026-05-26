<?php
session_start();

// Trava de segurança: Se não estiver logado, expulsa para o login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../config/conexao.php';

$usuario_id = $_SESSION['usuario_id'];

// 1. Busca os dados biométricos do usuário que acabaram de ser cadastrados
$stmt = $pdo->prepare("SELECT * FROM biometria WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$biometria = $stmt->fetch();

if (!$biometria) {
    // Se por acaso não houver biometria, manda preencher o cadastro
    header("Location: cadastro.php");
    exit;
}

// 2. CÁLCULO AUTOMÁTICO (Equação de Harris-Benedict Revisada)
$peso = $biometria['peso'];
$altura_cm = $biometria['altura'] * 100; // Converte de volta para cm se necessário para a fórmula
$idade = $biometria['idade'];
$genero = $biometria['genero'];
$nivel_atividade = $biometria['nivel_atividade'];
$objetivo = $biometria['objetivo'];

// Cálculo da Taxa Metabólica Basal (TMB)
if ($genero === 'Masculino') {
    $tmb = 88.36 + (13.4 * $peso) + (4.8 * $altura_cm) - (5.7 * $idade);
} else {
    $tmb = 447.59 + (9.2 * $peso) + (3.1 * $altura_cm) - (4.3 * $idade);
}

// Fator de Atividade para chegar ao Gasto Energético Total (GET)
$fatores = [
    'Sedentario'   => 1.2,
    'Leve'         => 1.375,
    'Moderado'     => 1.55,
    'Ativo'        => 1.725,
    'Muito_Ativo'  => 1.9
];
$fator = $fatores[$nivel_atividade] ?? 1.2;
$get = $tmb * $fator; // Gasto calórico diário para manter o peso

// Ajuste de Calorias baseado no Objetivo
$meta_calorica = $get;
if ($objetivo === 'Perda_Peso') {
    $meta_calorica = $get - 500; // Déficit calórico padrão seguro
} elseif ($objetivo === 'Ganho_Massa') {
    $meta_calorica = $get + 400; // Superávit calórico padrão
}

// Arredonda os valores para exibição limpa
$meta_final_auto = round($meta_calorica);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Metas - NutrIA</title>
    <style>
        /* Garante que a seção manual comece escondida de forma correta */
        #sectionManual {
            display: none;
            margin-top: 15px;
        }
    </style>
</head>
<body>

<div>
    <h1>Definição de Metas</h1>

    <div>
        <h2>Sua Meta Calórica Recomendada por IA</h2>
        <div><?php echo $meta_final_auto; ?> kcal</div>
        <p>Calculado com base no seu objetivo de: <strong><?php echo str_replace('_', ' ', $objetivo); ?></strong></p>
    </div>

    <form action="../controllers/metas_controller.php" method="POST">
        <input type="hidden" name="tipo_meta" value="automatica">
        <input type="hidden" name="calorias_calculadas" value="<?php echo $meta_final_auto; ?>">
        <button type="submit">Aceitar e Ir para o Dashboard</button>
    </form>

    <br>
    <a href="#" id="btnManual">Já sei meu gasto / Quero definir manualmente</a>
    <br>

    <div id="sectionManual">
        <form action="../controllers/metas_controller.php" method="POST">
            <input type="hidden" name="tipo_meta" value="manual">
            
            <div>
                <label for="icalorias_man">Digite sua Meta Calórica Diária (kcal):</label>
                <input type="number" id="icalorias_man" name="calorias_manuais" placeholder="Ex: 2200" required>
            </div>

            <button type="submit">Salvar Meta Personalizada</button>
        </form>
    </div>
</div>

<script>
    // JavaScript para abrir/fechar a área manual sem recarregar a página
    const btnManual = document.getElementById('btnManual');
    const sectionManual = document.getElementById('sectionManual');

    btnManual.addEventListener('click', function(e) {
        e.preventDefault(); // Evita que a página mude de foco/role para o topo
        if (sectionManual.style.display === 'block') {
            sectionManual.style.display = 'none';
        } else {
            sectionManual.style.display = 'block';
            sectionManual.scrollIntoView({ behavior: 'smooth' });
        }
    });
</script>

</body>
</html>