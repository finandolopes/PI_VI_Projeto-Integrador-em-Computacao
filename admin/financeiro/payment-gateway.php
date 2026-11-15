<?php
/** Rota legada desativada: implementação anterior não corresponde ao esquema atual. */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../php/seguranca.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
verificarSessao();
require_permissao(['admin', 'gestor']);
http_response_code(503);
echo json_encode(['success'=>false,'message'=>'Rota legada desativada por segurança. Utilize os módulos atuais de clientes, requisições e financeiro. Nenhuma transação foi realizada.'], JSON_UNESCAPED_UNICODE);
