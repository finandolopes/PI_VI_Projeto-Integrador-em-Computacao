<?php
session_start();
include_once('conexao.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verificar se os campos necessários estão definidos
    if (isset($_POST["id_depoimento"], $_POST["acao"])) {
        $id_depoimento = (int) $_POST["id_depoimento"];
        $acao = (string) $_POST["acao"]; // Ação pode ser "aprovar" ou "reprovar"

        // Definir o status_mod com base na ação
        $status_mod = ($acao === "aprovar") ? "aprovado" : "reprovado";

        try {
            // Atualizar o status_mod na tabela depoimentos
            $sql_update = "UPDATE depoimentos SET status_mod = ? WHERE id = ?";
            $stmt_update = $conexao->prepare($sql_update);
            if ($stmt_update) {
                $stmt_update->bind_param("si", $status_mod, $id_depoimento);
                $stmt_update->execute();
                $stmt_update->close();
            }

            // Definir uma variável de sessão para armazenar a mensagem de sucesso
            $_SESSION['sucesso_atualizacao'] = true;
        } catch (Exception $e) {
            echo "Erro: " . $e->getMessage();
        }

        // Fechar a conexão com o banco de dados
        $conexao->close();

        // Redirecionar de volta para onde você estava
        $destino = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../admin/mod_depoimentos.php';
        header("Location: {$destino}");
        exit();
    } else {
        echo "Campos obrigatórios não foram definidos.";
    }
}
?>
