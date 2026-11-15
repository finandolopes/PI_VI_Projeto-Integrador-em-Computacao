<?php
// processa_reset_senha.php - Processa solicitações de reset de senha
session_start();
include_once('../php/conexao.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mysqli_real_escape_string($con, $_POST['email']);

    // Verificar se o email existe na tabela usuarios
    $query = "SELECT id, nome, usuario FROM usuarios WHERE email = '$email'";
    $result = mysqli_query($con, $query);

    if (mysqli_num_rows($result) > 0) {
        $usuario = mysqli_fetch_assoc($result);

        // Gerar token único
        $token = bin2hex(random_bytes(32));

        // Inserir solicitação na tabela reset_senha
        $insert_query = "INSERT INTO reset_senha (id_usuario, email, token, status)
                        VALUES ('{$usuario['id']}', '$email', '$token', 'pendente')";

        if (mysqli_query($con, $insert_query)) {
            // Aqui você pode implementar o envio de email
            // Por enquanto, apenas armazenamos a solicitação

            $_SESSION['reset_success'] = "Solicitação de reset de senha enviada com sucesso! O administrador será notificado.";
            header('Location: ../index.php?reset=success');
            exit();
        } else {
            $_SESSION['reset_error'] = "Erro ao processar solicitação. Tente novamente.";
            header('Location: ../index.php?reset=error');
            exit();
        }
    } else {
        $_SESSION['reset_error'] = "Email não encontrado em nossa base de dados.";
        header('Location: ../index.php?reset=error');
        exit();
    }
} else {
    header('Location: ../index.php');
    exit();
}
?>
