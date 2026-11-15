<?php
/** Testes puros V7: não exigem MySQL nem rede. */
require_once __DIR__.'/../php/access_policy_v7.php';
require_once __DIR__.'/../php/crm_service_v7.php';
require_once __DIR__.'/../php/finance_service_v7.php';
function eq(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('FALHOU: '.$label);echo "OK: $label\n";}
$admin=['username'=>'admin','perfil'=>'admin'];$tech=['username'=>'tecnico','perfil'=>'tecnico'];$commercial=['username'=>'comercial','perfil'=>'comercial'];$visitor=['username'=>'x','perfil'=>'usuario'];
eq(cfCan('admin.manage',$admin),'Admin permitido');
eq(!cfCan('admin.manage',$tech),'Técnico não administra');
eq(cfCan('sd.manage',$tech)&&!cfCan('finance.view',$tech),'Segregação técnica x financeiro');
eq(cfCan('crm.view',$commercial)&&!cfCan('iot.view',$commercial),'Segregação comercial');
eq(!cfCan('finance.view',$visitor),'Usuário sem financeiro');
eq(!cfCan('work.view',[]),'Não autenticado negado');
eq(cfCrmTransitionAllowed('pendente','em_analise'),'CRM pendente para análise');
eq(!cfCrmTransitionAllowed('aprovado','pendente'),'CRM terminal não regride');
$base=['entry_type'=>'receber','description'=>'Teste de lançamento interno','amount'=>'149.50','due_date'=>'2026-11-09','idempotency_key'=>str_repeat('b',64)];
$v=cfFinanceValidateDraft($base);eq($v['amount']==='149.50'&&$v['due']==='2026-11-09','Valor monetário e vencimento');
foreach([['amount'=>'-1'],['amount'=>'1e8'],['due_date'=>'2026-02-30'],['idempotency_key'=>'abc']] as $override){try{cfFinanceValidateDraft(array_replace($base,$override));throw new RuntimeException('Aceitou entrada inválida');}catch(InvalidArgumentException $e){echo "OK: entrada inválida rejeitada\n";}}
$raw=file_get_contents(__DIR__.'/../.env.example');preg_match_all('/^([A-Z][A-Z0-9_]*)=/m',$raw,$m);eq(count($m[1])===count(array_unique($m[1])),'Variáveis .env.example não duplicadas');
$schema=file_get_contents(__DIR__.'/../sql/v7/001_core_baseline.sql');eq(!preg_match('/^\s*DROP\s+TABLE\b/im',$schema)&&!preg_match('/^\s*INSERT\s+INTO\b/im',$schema),'Baseline livre de DROP e seeds');
foreach(['010_iot_core.sql','020_v7_operations.sql','030_service_desk.sql','040_notifications.sql'] as $name)eq(is_file(__DIR__.'/../sql/v7/'.$name),'Migração '. $name);

require_once __DIR__.'/../php/payment_contract_v7.php';
eq(strlen(cfPaymentEventKey('provider_demo','evt_1'))===64 && cfPaymentEventKey('provider_demo','evt_1')===cfPaymentEventKey('provider_demo','evt_1'),'Chave idempotente de evento de pagamento (contrato, sem gateway)');
