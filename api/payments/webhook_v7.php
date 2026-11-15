<?php
/** Nenhum gateway homologado: rejeita webhooks, nunca confirma pagamento fictício. */
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
http_response_code(501);echo json_encode(['ok'=>false,'error'=>'Integração de pagamento não configurada: provedor, assinatura, reconciliação e testes obrigatórios.'],JSON_UNESCAPED_UNICODE);
