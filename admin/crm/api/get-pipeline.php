<?php
require_once __DIR__.'/../../../php/crm_service_v7.php';
cfRequirePermission('crm.view');header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);echo json_encode(['ok'=>false,'error'=>'Método inválido']);exit;}
try{$rows=cfCrmPipeline(cfDb());echo json_encode(['ok'=>true,'items'=>$rows],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);}catch(Throwable $e){error_log('CRM V7 lista: '.$e->getMessage());http_response_code(503);echo json_encode(['ok'=>false,'error'=>'Dados temporariamente indisponíveis']);}
