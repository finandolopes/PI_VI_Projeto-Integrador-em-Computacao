<?php
session_start();
require_once __DIR__.'/../php/sd_authorization.php';
sdRequireRole('technician');
require_once __DIR__.'/../php/conexao.php';
require_once __DIR__.'/../php/sd_service.php';
function sdDetailEsc($v): string {return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');}
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if(!$id || $id<1){http_response_code(400);exit('Identificador do chamado inválido.');}
$message='';$ticket=null;$history=[];$events=[];
if(empty($_SESSION['sd_detail_csrf']))$_SESSION['sd_detail_csrf']=bin2hex(random_bytes(24));
try{
 sdSchema($conexao);
 if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  if(!hash_equals((string)$_SESSION['sd_detail_csrf'],(string)($_POST['csrf']??'')))throw new InvalidArgumentException('Token de segurança inválido.');
  sdUpdate($conexao,(int)$id,(string)($_POST['status']??''),mb_substr(trim((string)($_POST['assigned_to']??'')),0,130),(string)($_POST['note']??''),(string)($_SESSION['username']??$_SESSION['user_id']??'tecnico'));
  header('Location: service-desk-detalhe.php?id='.$id.'&updated=1');exit;
 }
 $q=$conexao->prepare('SELECT * FROM sd_tickets WHERE id=?');$q->bind_param('i',$id);$q->execute();$ticket=$q->get_result()->fetch_assoc();$q->close();
 if(!$ticket){http_response_code(404);exit('Chamado não encontrado.');}
 $q=$conexao->prepare('SELECT actor,event_type,old_value,new_value,note,created_at FROM sd_ticket_history WHERE ticket_id=? ORDER BY created_at ASC,id ASC');$q->bind_param('i',$id);$q->execute();$history=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();
 if($ticket['origin']==='iot'){$q=$conexao->prepare('SELECT outcome,device_id,telemetry_id,rule_id,created_at FROM sd_iot_rule_events WHERE ticket_id=? ORDER BY created_at ASC,id ASC');$q->bind_param('i',$id);$q->execute();$events=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();}
 $sla=sdSlaStatus($ticket);
}catch(Throwable $e){error_log('[SD detail] '.$e->getMessage());$message='Não foi possível carregar o chamado. Verifique a conexão e o esquema do banco.';}
?><!doctype html><html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Chamado <?=sdDetailEsc($ticket['protocol']??'')?> | CONFINTER</title><link rel="stylesheet" href="assets/css/bootstrap.min.css"><link rel="stylesheet" href="assets/css/style.css"><link rel="stylesheet" href="assets/css/confinter-admin-refinement.css"><link rel="stylesheet" href="assets/css/confinter-operations-2026.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"><link rel="stylesheet" href="assets/css/confinter-admin-hardening.css">
</head><body>
<?php include __DIR__.'/navbar.php';?>
<div class="page-wrapper"><main class="content container-fluid ops-content" id="main-content"><div class="ops-heading"><div><p class="ops-eyebrow">Service Desk / Detalhamento</p><h1>Chamado <?=sdDetailEsc($ticket['protocol']??'')?></h1><p>Acompanhe o incidente desde a origem até a resolução, com histórico auditável.</p></div><a class="ops-link" href="service-desk.php">← Voltar para chamados</a></div>
<?php if(isset($_GET['updated'])):?><div class="ops-notice" role="status">Chamado atualizado e registrado no histórico.</div><?php endif;?>
<?php if($message):?><div class="ops-notice" role="alert"><?=sdDetailEsc($message)?></div><?php elseif($ticket):?>
<section class="ops-statgrid" aria-label="Indicadores do chamado">
<article><span>Status</span><strong><?=sdDetailEsc(str_replace('_',' ',(string)$ticket['status']))?></strong></article>
<article><span>Prioridade</span><strong><?=sdDetailEsc($ticket['priority'])?></strong></article>
<article><span>Origem</span><strong><?=sdDetailEsc($ticket['origin'])?></strong></article>
<article><span>SLA resposta</span><strong class="<?= $sla['response_breached']?'ops-danger':'ops-ok' ?>"><?= $sla['response_breached']?'Excedido':'Dentro do prazo' ?></strong></article>
</section>
<div class="ops-columns"><section class="ops-panel"><h2>Informações do chamado</h2><dl class="ops-info"><div><dt>Assunto</dt><dd><?=sdDetailEsc($ticket['title'])?></dd></div><div><dt>Categoria</dt><dd><?=sdDetailEsc($ticket['category'])?></dd></div><div><dt>Solicitante</dt><dd><?=sdDetailEsc($ticket['requester_name'])?></dd></div><div><dt>Abertura</dt><dd><?=sdDetailEsc($ticket['created_at'])?></dd></div><div><dt>Primeira resposta</dt><dd><?=sdDetailEsc($ticket['first_response_at']??'Pendente')?></dd></div><div><dt>Resolução</dt><dd><?=sdDetailEsc($ticket['resolved_at']??'Pendente')?></dd></div><?php if($ticket['origin']==='iot'):?><div><dt>Dispositivo</dt><dd><?=sdDetailEsc($ticket['device_id'])?></dd></div><div><dt>ID da leitura</dt><dd><?=sdDetailEsc($ticket['telemetry_id'])?></dd></div><div><dt>ID da regra</dt><dd><?=sdDetailEsc($ticket['rule_id'])?></dd></div><?php endif;?></dl><h3>Descrição</h3><p class="ops-description"><?=nl2br(sdDetailEsc($ticket['description']))?></p></section>
<section class="ops-panel"><h2>Atendimento técnico</h2><form class="ops-form" method="post"><input type="hidden" name="csrf" value="<?=sdDetailEsc($_SESSION['sd_detail_csrf'])?>"><label>Status<select name="status" required><?php foreach(['aberto'=>'Aberto','em_atendimento'=>'Em atendimento','aguardando'=>'Aguardando','resolvido'=>'Resolvido','fechado'=>'Fechado'] as $v=>$label):?><option value="<?=$v?>" <?=($ticket['status']===$v?'selected':'')?>><?=$label?></option><?php endforeach;?></select></label><label>Responsável<input type="text" maxlength="130" name="assigned_to" value="<?=sdDetailEsc($ticket['assigned_to'])?>"></label><label>Nota de atendimento<textarea name="note" maxlength="3000" rows="5" placeholder="Registre a análise, ações executadas e resultado observado."></textarea></label><button class="ops-button" type="submit">Salvar atualização</button></form><p class="ops-hint">Toda alteração é auditada. Para resolver ou encerrar, registre uma nota de pelo menos 10 caracteres; para reabrir um chamado fechado, informe a justificativa.</p><h3>Prazos de SLA</h3><p>Resposta: <?=sdDetailEsc($sla['response_deadline'])?></p><p>Resolução: <?=sdDetailEsc($sla['resolution_deadline'])?></p></section></div>
<section class="ops-panel ops-timeline-panel"><h2>Linha do tempo e auditoria</h2><ol class="ops-timeline"><?php foreach($history as $h):?><li><time><?=sdDetailEsc($h['created_at'])?></time><strong><?=sdDetailEsc($h['event_type'])?></strong><p>Responsável: <?=sdDetailEsc($h['actor'])?></p><?php if($h['new_value']):?><p><?=sdDetailEsc($h['new_value'])?></p><?php endif;?><?php if($h['note']):?><p><?=nl2br(sdDetailEsc($h['note']))?></p><?php endif;?></li><?php endforeach;?></ol><?php if(!$history):?><p>Nenhum evento de auditoria encontrado.</p><?php endif;?></section>
<?php if($ticket['origin']==='iot'):?><section class="ops-panel"><h2>Telemetria e regras relacionadas</h2><div class="ops-table-wrap"><table class="ops-table"><thead><tr><th>Data</th><th>Dispositivo</th><th>Leitura</th><th>Regra</th><th>Decisão</th></tr></thead><tbody><?php foreach($events as $e):?><tr><td><?=sdDetailEsc($e['created_at'])?></td><td><?=sdDetailEsc($e['device_id'])?></td><td><?=sdDetailEsc($e['telemetry_id'])?></td><td><?=sdDetailEsc($e['rule_id'])?></td><td><?=sdDetailEsc($e['outcome']==='created'?'Chamado criado':'Duplicidade suprimida')?></td></tr><?php endforeach;?></tbody></table></div><?php if(!$events):?><p>Nenhum evento IoT relacionado encontrado.</p><?php endif;?></section><?php endif;?>
<?php endif;?></main></div>
<?php include __DIR__.'/footer.php';?></body></html>
