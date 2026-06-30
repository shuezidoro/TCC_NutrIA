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
    header("Location: cadastro.php");
    exit;
}

// 2. CÁLCULO AUTOMÁTICO (Equação de Harris-Benedict Revisada)
$peso = $biometria['peso'];
$altura_cm = $biometria['altura'] * 100; 
$idade = $biometria['idade'];
$genero = $biometria['genero'];
$nivel_atividade = $biometria['nivel_atividade'];
$objetivo = $biometria['objetivo'];

if ($genero === 'Masculino') {
    $tmb = 88.36 + (13.4 * $peso) + (4.8 * $altura_cm) - (5.7 * $idade);
} else {
    $tmb = 447.59 + (9.2 * $peso) + (3.1 * $altura_cm) - (4.3 * $idade);
}

$fatores = [
    'Sedentario'   => 1.2,
    'Leve'         => 1.375,
    'Moderado'     => 1.55,
    'Ativo'        => 1.725,
    'Muito_Ativo'  => 1.9
];
$fator = $fatores[$nivel_atividade] ?? 1.2;
$get = $tmb * $fator; 

$meta_calorica = $get;
if ($objetivo === 'Perda_Peso') {
    $meta_calorica = $get - 500; 
} elseif ($objetivo === 'Ganho_Massa') {
    $meta_calorica = $get + 400; 
}

$meta_final_auto = round($meta_calorica);

// Distribuição de Macronutrientes Automática (Padrão: 40% Carbo, 30% Proteína, 30% Gordura)
// 1g Carbo = 4kcal | 1g Prot = 4kcal | 1g Gord = 9kcal
$auto_carbo = round(($meta_final_auto * 0.40) / 4);
$auto_prot  = round(($meta_final_auto * 0.30) / 4);
$auto_gord  = round(($meta_final_auto * 0.30) / 9);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../public/css/metas_style.css">
    <title>Minhas Metas - NutrIA</title>
</head>
<body>

<div>
    <h1>Definição de Metas</h1>

    <div>
        <h2>Sua Meta Calórica Recomendada por IA</h2>
        <div><?php echo $meta_final_auto; ?> kcal</div>
        <p>Calculado com base no seu objetivo de: <strong><?php echo str_replace('_', ' ', $objetivo); ?></strong></p>
        <p>Macros sugeridos: Carbo: <?=$auto_carbo?>g | Prot: <?=$auto_prot?>g | Gord: <?=$auto_gord?>g</p>
    </div>

    <form action="../controllers/metas_controller.php" method="POST">
        <input type="hidden" name="tipo_meta" value="automatica">
        <input type="hidden" name="calorias" value="<?php echo $meta_final_auto; ?>">
        <input type="hidden" name="proteina" value="<?php echo $auto_prot; ?>">
        <input type="hidden" name="carbo" value="<?php echo $auto_carbo; ?>">
        <input type="hidden" name="gordura" value="<?php echo $auto_gord; ?>">
        <button type="submit">Aceitar e Ir para o Dashboard</button>
    </form>

    <br>
    <a href="#" id="btnManual">Definir minhas metas</a>

    <div id="sectionManual" style="display: none; margin-top: 20px;">
        <form action="../controllers/metas_controller.php" method="POST">
            <input type="hidden" name="tipo_meta" value="manual">
            
            <div>
                <label for="icalorias_man">Meta Calórica Diária (kcal):</label>
                <input type="number" id="icalorias_man" name="calorias" placeholder="Ex: 2200" required>
            </div>
            <div>
                <label for="iprot_man">Proteínas (g):</label>
                <input type="number" id="iprot_man" name="proteina" placeholder="Ex: 150" required>
            </div>
            <div>
                <label for="icarbo_man">Carboidratos (g):</label>
                <input type="number" id="icarbo_man" name="carbo" placeholder="Ex: 200" required>
            </div>
            <div>
                <label for="igord_man">Gorduras (g):</label>
                <input type="number" id="igord_man" name="gordura" placeholder="Ex: 70" required>
            </div>

            <button type="submit">Salvar Meta Personalizada</button>
        </form>
    </div>
</div>

<script>
    const btnManual = document.getElementById('btnManual');
    const sectionManual = document.getElementById('sectionManual');

    btnManual.addEventListener('click', function(e) {
        e.preventDefault(); 
        if (sectionManual.style.display === 'block') {
            sectionManual.style.display = 'none';
        } else {
            sectionManual.style.display = 'block';
        }
    });
</script>

</body>
</html>