<?php
session_start();

// Importação correta usando caminho relativo baseado na pasta atual deste arquivo
require_once __DIR__ . '/../config/conexao.php';

// Verifica se a requisição veio via formulário POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // ==========================================
    // LÓGICA DE CADASTRO COMPLETO
    // ==========================================\
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
            $pdo->beginTransaction();

            // 1. Verifica se o e-mail já existe na tabela 'usuarios'
            $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmtCheck->execute([$email]);
            if ($stmtCheck->fetch()) {
                header("Location: ../views/cadastro.php?erro_email=1");
                exit;
            }

            // 2. Insere o novo usuário criptografando a senha
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmtUser = $pdo->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)");
            $stmtUser->execute([$nome, $email, $senha_hash]);

            $usuario_id = $pdo->lastInsertId();

            // 3. Insere os dados biométricos do usuário
            $stmtBio = $pdo->prepare("INSERT INTO biometria (usuario_id, peso, altura, idade, genero, nivel_atividade, objetivo) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtBio->execute([$usuario_id, $peso, $altura, $idade, $genero, $nivel_atividade, $objetivo]);

            $pdo->commit();

            // Auto-login após o cadastro bem-sucedido
            $_SESSION['usuario_id']   = $usuario_id;
            $_SESSION['usuario_nome'] = $nome;

            header("Location: ../views/dashboard.php");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            die("Erro técnico ao tentar cadastrar: " . $e->getMessage());
        }
    }

    // ==========================================
    // LÓGICA DE LOGIN TRADICIONAL
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

    // ==========================================
    // LÓGICA DE SOLICITAÇÃO DE RECUPERAÇÃO DE SENHA
    // ==========================================
    if ($acao === 'solicitar_recuperacao') {
        $email = trim($_POST['email']);

        try {
            // Verifica se o e-mail existe no sistema
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            if ($usuario) {
                // Gera um token criptograficamente seguro de 64 caracteres hexadecimais
                $token = bin2hex(random_bytes(32));
                
                // Define o tempo limite de expiração (15 minutos)
                $expira_em = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                // Salva o pedido na tabela de tokens
                $stmtToken = $pdo->prepare("INSERT INTO recuperacao_senha (usuario_id, token, expira_em) VALUES (?, ?, ?)");
                $stmtToken->execute([$usuario['id'], $token, $expira_em]);

                // Monta o link para o ambiente de testes (localhost)
                $link = "http://localhost/nutria/views/redefinir_senha.php?token=" . $token;

                // Em ambiente de produção, aqui você usaria uma biblioteca de e-mail (ex: PHPMailer)
                die("<h3>[Simulação de E-mail] NutrIA - Recuperação de Senha</h3>
                     <p>Um link de redefinição foi solicitado para a sua conta. Use o link abaixo para criar uma nova senha (válido por 15 min):</p>
                     <p><a href='$link' style='padding: 10px; background: #007bff; color: white; text-decoration: none;'>Redefinir Minha Senha</a></p>");
            } else {
                // Mensagem genérica preventiva para não expor a existência de e-mails no banco
                die("Se o e-mail estiver cadastrado no sistema, as instruções de redefinição foram enviadas.");
            }
        } catch (Exception $e) {
            die("Erro no servidor: " . $e->getMessage());
        }
    }

    // ==========================================
    // LÓGICA DE ATUALIZAÇÃO EFETIVA DA SENHA
    // ==========================================
    if ($acao === 'atualizar_senha') {
        $token = $_POST['token'] ?? '';
        $nova_senha = $_POST['nova_senha'] ?? '';

        try {
            // Validação secundária rigorosa diretamente no banco de dados para evitar requisições forjadas
            $stmt = $pdo->prepare("SELECT * FROM recuperacao_senha WHERE token = ? AND usado = 0 AND expira_em > NOW()");
            $stmt->execute([$token]);
            $pedido = $stmt->fetch();

            if ($pedido) {
                // Cria o novo Hash seguro com password_hash
                $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);

                $pdo->beginTransaction();

                // 1. Atualiza as credenciais na tabela oficial de usuários
                $stmtUpdate = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                $stmtUpdate->execute([$senha_hash, $pedido['usuario_id']]);

                // 2. Invalida o token atual (muda o estado para usado)
                $stmtInvalida = $pdo->prepare("UPDATE recuperacao_senha SET usado = 1 WHERE id = ?");
                $stmtInvalida->execute([$pedido['id']]);

                $pdo->commit();

                // Redireciona de volta para a tela de login informando o sucesso
                header("Location: ../views/login.php?senha_alterada=1");
                exit;
            } else {
                die("Erro: Esta solicitação de redefinição é inválida ou já se encontra expirada.");
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            die("Erro ao atualizar a senha: " . $e->getMessage());
        }
    }

} else {
    // Bloqueia acessos diretos via barra de endereço (GET) redirecionando para a raiz do projeto
    header("Location: ../../index.php");
    exit;
}