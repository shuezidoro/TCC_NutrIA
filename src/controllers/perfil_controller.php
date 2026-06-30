<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';

// Bloqueia acessos que não sejam via formulário POST ou de usuários deslogados
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['usuario_id'])) {
    header("Location: ../views/login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Coleta e sanitiza os dados enviados pelo formulário
$nome            = trim($_POST['nome']);
$idade           = intval($_POST['idade']);
$peso            = floatval($_POST['peso']);
$altura          = floatval($_POST['altura']);
$genero          = $_POST['genero'];
$nivel_atividade = $_POST['nivel_atividade'];
$objetivo        = $_POST['objetivo'];

// Converte a altura de centímetros para metros se o usuário digitou ex: 175
if ($altura > 3) {
    $altura = $altura / 100;
}

try {
    // Inicia uma transação para garantir que tudo mude junto no banco
    $pdo->beginTransaction();

    // 1. Atualiza o nome na tabela 'usuarios'
    $stmtUser = $pdo->prepare("UPDATE usuarios SET nome = ? WHERE id = ?");
    $stmtUser->execute([$nome, $usuario_id]);
    
    // Atualiza o nome gravado na sessão ativa
    $_SESSION['usuario_nome'] = $nome;

    // 2. Busca o peso atual registrado antes de atualizar para saber se mudou
    $stmtCheckPeso = $pdo->prepare("SELECT peso FROM biometria WHERE usuario_id = ? LIMIT 1");
    $stmtCheckPeso->execute([$usuario_id]);
    $pesoAtual = $stmtCheckPeso->fetchColumn();

    // 3. Atualiza os dados na tabela 'biometria'
    $sqlBio = "UPDATE biometria SET 
                peso = ?, 
                altura = ?, 
                idade = ?, 
                genero = ?, 
                nivel_atividade = ?, 
                objetivo = ? 
               WHERE usuario_id = ?";
    $stmtBio = $pdo->prepare($sqlBio);
    $stmtBio->execute([$peso, $altura, $idade, $genero, $nivel_atividade, $objetivo, $usuario_id]);

    // 4. REGRA INTELIGENTE: Se o peso mudou (ou não existia histórico), alimenta a evolução de peso
   // 4. REGRA INTELIGENTE: Se o peso mudou (ou não existia histórico), alimenta a evolução de peso
    if ($pesoAtual === false || floatval($pesoAtual) !== $peso) {
        // Mudamos o INSERT para atualizar o peso caso o usuário mude de ideia no mesmo dia
        $sqlHist = "INSERT INTO historico_peso (usuario_id, peso, data_registro) 
                    VALUES (?, ?, NOW()) 
                    ON DUPLICATE KEY UPDATE peso = VALUES(peso)";
        
        $stmtHist = $pdo->prepare($sqlHist);
        $stmtHist->execute([$usuario_id, $peso]);
    }

    // ==========================================
    // NOVA LINHA ADICIONADA AQUI:
    // Limpa a meta antiga para forçar o recalculo no Dashboard
    // ==========================================
    $stmtDeleteMetas = $pdo->prepare("DELETE FROM metas_diarias WHERE usuario_id = ?");
    $stmtDeleteMetas->execute([$usuario_id]);

    // Confirma todas as alterações no banco de dados
    $pdo->commit();

    // Redireciona de volta para o perfil informando o sucesso
    header("Location: ../views/perfil.php?sucesso=perfil_atualizado");
    exit;

} catch (Exception $e) {
    // Cancela as alterações em caso de falha crítica
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Erro ao atualizar o perfil: " . $e->getMessage());
}