<?php
require_once __DIR__.'/../php/sd_policies.php';
function assertion(bool $pass,string $msg):void {if(!$pass)throw new RuntimeException($msg);echo "PASS: $msg\n";}
function rejected(callable $fn):bool {try{$fn();}catch(InvalidArgumentException){return true;}return false;}
assertion(rejected(fn()=>sdValidateTransition('aberto','fechado','')), 'impede encerramento sem fluxo');
assertion(rejected(fn()=>sdValidateTransition('aberto','resolvido','ok')), 'exige nota de resolução');
assertion(rejected(fn()=>sdValidateTransition('fechado','em_atendimento','')), 'exige justificativa de reabertura');
sdValidateTransition('aberto','em_atendimento','');
sdValidateTransition('em_atendimento','resolvido','Corrigida a falha após verificação.');
sdValidateTransition('resolvido','fechado','Atendimento validado pelo solicitante.');
assertion(true,'transições válidas');
foreach(['=SUM(1,1)','+cmd','-4+5','@HYPERLINK','  =cmd',"\t=cmd"] as $v) assertion(str_starts_with(sdSafeCsvValue($v),"'"),'CSV bloqueia fórmula '.$v);
assertion(sdSafeCsvValue('Atendimento concluído')==='Atendimento concluído','CSV não altera texto seguro');
assertion(sdIsSelfDelete(['usuario_id'=>11],11),'evita autodeleção com ID legado');
assertion(!sdIsSelfDelete(['user_id'=>10],11),'permite alvo distinto');
