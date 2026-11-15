<?php
require_once __DIR__.'/../../../php/crm_service_v7.php';
cfRequirePermission('crm.manage');header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);echo json_encode(['ok'=>false,'error'=>'Método inválido']);exit;}
$token=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??$_POST['csrf_token']??'');
if(!cfCheckCsrf($token)){http_response_code(419);echo json_encode(['ok'=>false,'error'=>'Token CSRF inválido']);exit;}
$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
try{if(!$id)throw new InvalidArgumentException('Identificador inválido');$result=cfCrmSetStage(cfDb(),$id,(string)($_POST['status']??''),(string)($_POST['note']??''),cfActor());echo json_encode(['ok'=>true,'result'=>$result]);}
catch(InvalidArgumentException $e){http_response_code(422);echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}
catch(Throwable $e){error_log('CRM V7 alterar: '.$e->getMessage());http_response_code(503);echo json_encode(['ok'=>false,'error'=>'Operação não disponível ou pendente de homologação']);}
