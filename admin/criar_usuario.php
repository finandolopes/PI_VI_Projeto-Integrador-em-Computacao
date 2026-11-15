<?php
include_once('../php/conexao.php');

// FunÃ§Ã£o para criar um novo usuÃ¡rio
function criarUsuario($conexao, $nome, $usuario, $senha, $telefone, $email, $perfil) {
    // Hash da senha
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
    
    // Query SQL para inserir o novo usuÃ¡rio
    $sql = "INSERT INTO usuarios (nome, usuario, senha, telefone, email, perfil) VALUES ('$nome', '$usuario', '$senhaHash', '$telefone', '$email', '$perfil')";
    
    // Executa a query
    if (mysqli_query($conexao, $sql)) {
        return true; // Retorna verdadeiro se a inserÃ§Ã£o for bem-sucedida
    } else {
        return false; // Retorna falso se houver algum erro na inserÃ§Ã£o
    }
}

// Verifica se o formulÃ¡rio foi submetido
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // ObtÃ©m os valores do formulÃ¡rio
    $nome = $_POST["nome"];
    $usuario = $_POST["usuario"];
    $senha = $_POST["senha"];
    $telefone = $_POST["telefone"];
    $email = $_POST["email"];
    $perfil = $_POST["perfil"];

    // Chama a funÃ§Ã£o criarUsuario para inserir o novo usuÃ¡rio no banco de dados
    if (criarUsuario($conn, $nome, $usuario, $senha, $telefone, $email, $perfil)) {
        echo "UsuÃ¡rio criado com sucesso";
    } else {
        echo "Erro ao criar usuÃ¡rio";
    }

    // Fecha a conexÃ£o com o banco de dados
    $conexao->close();
}
?>
