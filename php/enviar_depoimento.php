<?php
session_start();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit('Método não permitido'); }
require __DIR__ . '/conexao.php';
$nome = trim((string)($_POST['nome'] ?? '')) ?: 'Anônimo';
$mensagem = trim((string)($_POST['mensagem'] ?? ''));
if ($mensagem === '' || mb_strlen($mensagem) > 10000 || mb_strlen($nome) > 130) {
    $_SESSION['erro_mensagem']='Confira o nome e a mensagem.';
} elseif (!($conexao instanceof mysqli)) {
    $_SESSION['erro_mensagem']='Serviço de depoimentos temporariamente indisponível.';
} else {
    try {
        $stmt=$conexao->prepare("INSERT INTO depoimentos (nome_cliente, mensagem, status_mod) VALUES (?, ?, 'pendente')");
        $stmt->bind_param('ss',$nome,$mensagem);$stmt->execute();$stmt->close();
        $_SESSION['sucesso_depoimento']=true;
    } catch (Throwable $e) {error_log('[Depoimento] '.$e->getMessage());$_SESSION['erro_mensagem']='Não foi possível registrar o depoimento.';}
}
$referer=(string)($_SERVER['HTTP_REFERER']??'');
$path=(string)(parse_url($referer,PHP_URL_PATH)??'');
header('Location: '.($path!=='' && str_starts_with($path,'/') && !str_starts_with($path,'//')?$path:'../index.php'));exit;
