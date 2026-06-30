<?php
session_start();

// Importação correta usando caminho relativo baseado na pasta atual deste arquivo
require_once __DIR__ . '/../config/conexao.php';

// Verifica se a requisição veio via formulário POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // ==========================================
    // LÓGICA DE CADASTRO COMPLETO (COM VALIDAÇÃO)
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
            $pdo->beginTransaction();

            // 1. Verifica se o e-mail já existe na tabela 'usuarios'
            $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmtCheck->execute([$email]);
            if ($stmtCheck->fetch()) {
                header("Location: ../views/cadastro.php?erro_email=1");
                exit;
            }

            // 2. Gera um token seguro para a ativação do e-mail
            $token_ativacao = bin2hex(random_bytes(32));

            // 3. Insere o novo usuário criptografando a senha (Inativo por padrão: ativo = 0)
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmtUser = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, ativo, token_ativacao) VALUES (?, ?, ?, 0, ?)");
            $stmtUser->execute([$nome, $email, $senha_hash, $token_ativacao]);

            $usuario_id = $pdo->lastInsertId();

            // 4. Insere os dados biométricos do usuário
            $stmtBio = $pdo->prepare("INSERT INTO biometria (usuario_id, peso, altura, idade, genero, nivel_atividade, objetivo) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtBio->execute([$usuario_id, $peso, $altura, $idade, $genero, $nivel_atividade, $objetivo]);

            $pdo->commit();

            // 5. Monta o link para validação (Ambiente Local XAMPP)
            $link_ativacao = "http://localhost/nutria/views/confirmar.php?token=" . $token_ativacao;

            // Simulação de e-mail (Substitua por PHPMailer quando for para produção)
            die("<h3>[Simulação de E-mail] NutrIA - Ativação de Conta</h3>
                 <p>Olá, $nome! Obrigado por se cadastrar. Para validar seu e-mail e acessar o sistema, clique no botão abaixo:</p>
                 <p><a href='$link_ativacao' style='padding: 10px; background: #28a745; color: white; text-decoration: none; font-weight: bold; border-radius: 4px;'>Confirmar Meu E-mail</a></p>");

        } catch (Exception $e) {
            $pdo->rollBack();
            die("Erro técnico ao tentar cadastrar: " . $e->getMessage());
        }
    }

    // ==========================================
    // LÓGICA DE LOGIN TRADICIONAL (VALIDANDO ATIVO)
    // ==========================================
    if ($acao === 'login') {
        $email = trim($_POST['email']);
        $senha = $_POST['senha'];

        try {
            // 1. Busca o usuário incluindo a coluna 'ativo'
            $stmt = $pdo->prepare("SELECT id, nome, senha, ativo FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            // 2. Verifica a existência do usuário e valida o hash da senha
            if ($usuario && password_verify($senha, $usuario['senha'])) {
                
                // 3. Bloqueia o login caso a conta não tenha sido ativada pelo e-mail
                if ((int)$usuario['ativo'] === 0) {
                    die("<h3>Acesso Bloqueado</h3><p>Você precisa validar o seu e-mail antes de realizar o primeiro login. Verifique sua caixa de entrada.</p><p><a href='../views/login.php'>Voltar</a></p>");
                }
                
                // Grava os dados do login na sessão do servidor
                $_SESSION['usuario_id']   = $usuario['id'];
                $_SESSION['usuario_nome'] = $usuario['nome'];

                // Redirecionamento instantâneo para a dashboard de controle
                header("Location: ../views/dashboard.php");
                exit;
                
            } else {
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
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            if ($usuario) {
                $token = bin2hex(random_bytes(32));
                $expira_em = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                $stmtToken = $pdo->prepare("INSERT INTO recuperacao_senha (usuario_id, token, expira_em) VALUES (?, ?, ?)");
                $stmtToken->execute([$usuario['id'], $token, $expira_em]);

                $link = "http://localhost/nutria/views/redefinir_senha.php?token=" . $token;

                die("<h3>[Simulação de E-mail] NutrIA - Recuperação de Senha</h3>
                     <p>Um link de redefinição foi solicitado para a sua conta. Use o link abaixo para criar uma nova senha (válido por 15 min):</p>
                     <p><a href='$link' style='padding: 10px; background: #007bff; color: white; text-decoration: none;'>Redefinir Minha Senha</a></p>");
            } else {
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
            $stmt = $pdo->prepare("SELECT * FROM recuperacao_senha WHERE token = ? AND usado = 0 AND expira_em > NOW()");
            $stmt->execute([$token]);
            $pedido = $stmt->fetch();

            if ($pedido) {
                $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);

                $pdo->beginTransaction();

                $stmtUpdate = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                $stmtUpdate->execute([$senha_hash, $pedido['usuario_id']]);

                $stmtInvalida = $pdo->prepare("UPDATE recuperacao_senha SET usado = 1 WHERE id = ?");
                $stmtInvalida->execute([$pedido['id']]);

                $pdo->commit();

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
    header("Location: ../../index.php");
    exit;
}