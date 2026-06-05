<?php
session_start();

// Importação correta usando caminho relativo baseado na pasta atual deste arquivo
require_once __DIR__ . '/../config/conexao.php';

// Verifica se a requisição veio via formulário POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // ==========================================
    // LÓGICA DE CADASTRO COMPLETO
    // ==========================================
    if ($acao === 'cadastro') {
        // Coleta dados básicos
        $nome  = trim($_POST['nome']);
        $email = trim($_POST['email']);
        $senha = $_POST['senha'];

        // Coleta dados biométricos
        $peso            = floatval($_POST['peso']);
        $altura          = floatval($_POST['altura']);
        $idade           = intval($_POST['idade'] ?? 0);
        $genero          = $_POST['sexo']; 
        $nivel_atividade = $_POST['atividade'];
        $objetivo        = $_POST['objetivo'];

        // Conversão de altura (centímetros para metros) se necessário
        if ($altura > 3) {
            $altura = $altura / 100;
        }

        try {
            // 1. Verifica se o e-mail já existe na tabela 'usuarios'
            $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmtCheck->execute([$email]);
            
            if ($stmtCheck->fetch()) {
                header("Location: ../views/login.php?erro_cadastro=email_existente");
                exit;
            }

            // Inicia uma transação no banco (Garante a consistência dos dados para o TCC)
            $pdo->beginTransaction();

            // 2. Criptografa a senha de forma segura
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

            // 3. Insere os dados na tabela 'usuarios'
            $sqlUsuario = "INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)";
            $stmtUsuario = $pdo->prepare($sqlUsuario);
            $stmtUsuario->execute([$nome, $email, $senhaHash]);

            // Recupera o ID gerado automaticamente
            $usuario_id = $pdo->lastInsertId();

            // 4. Insere os dados correspondentes na tabela 'biometria'
            $sqlBiometria = "INSERT INTO biometria (usuario_id, peso, altura, idade, genero, nivel_atividade, objetivo) 
                             VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmtBiometria = $pdo->prepare($sqlBiometria);
            $stmtBiometria->execute([
                $usuario_id, 
                $peso, 
                $altura, 
                $idade, 
                $genero, 
                $nivel_atividade, 
                $objetivo
            ]);

            // Confirma as alterações permanentemente no banco
            $pdo->commit();

            // Salva as variáveis na Sessão do PHP para manter o usuário logado
            $_SESSION['usuario_id'] = $usuario_id;
            $_SESSION['usuario_nome'] = $nome;

            // Redireciona direto para a página de metas pós-cadastro
            header("Location: ../views/metas.php");
            exit;

        } catch (Exception $e) {
            // Desfaz tudo caso algo falhe
            $pdo->rollBack();
            die("Erro crítico ao realizar cadastro: " . $e->getMessage());
        }
    }

    // ==========================================
    // LÓGICA DE LOGIN (Direto para a Dashboard)
    // ==========================================
    if ($acao === 'login') {
        $email = trim($_POST['email']);
        $senha = $_POST['senha'];

        try {
            // 1. Busca o usuário pelo e-mail na tabela 'usuarios'
            $stmt = $pdo->prepare("SELECT id, nome, senha FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            // 2. Verifica a existência do usuário e valida o hash da senha
            if ($usuario && password_verify($senha, $usuario['senha'])) {
                
                // Grava os dados do login na sessão do servidor
                $_SESSION['usuario_id']   = $usuario['id'];
                $_SESSION['usuario_nome'] = $usuario['nome'];

                // Redirecionamento instantâneo para a dashboard de controle
                header("Location: ../views/dashboard.php");
                exit;
                
            } else {
                // Falha de credenciais devolve o usuário para a tela com parâmetro de erro
                header("Location: ../views/login.php?erro_login=1");
                exit;
            }

        } catch (Exception $e) {
            die("Erro técnico ao tentar fazer login: " . $e->getMessage());
        }
    }

} else {
    // Bloqueia acessos diretos via barra de endereço (GET) redirecionando para a raiz do projeto
    header("Location: ../../index.php");
    exit;
}