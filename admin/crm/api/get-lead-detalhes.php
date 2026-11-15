<?php
require_once __DIR__.'/../../../php/crm_service_v7.php';
cfRequirePermission('crm.view');header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);echo json_encode(['ok'=>false,'error'=>'Método inválido']);exit;}
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$id){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'id inválido']);exit;}
try{$item=cfCrmDetail(cfDb(),$id);if(!$item){http_response_code(404);echo json_encode(['ok'=>false,'error'=>'Não encontrado']);exit;}echo json_encode(['ok'=>true,'item'=>$item],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);}catch(Throwable $e){error_log('CRM V7 detalhe: '.$e->getMessage());http_response_code(503);echo json_encode(['ok'=>false,'error'=>'Dados temporariamente indisponíveis']);}
