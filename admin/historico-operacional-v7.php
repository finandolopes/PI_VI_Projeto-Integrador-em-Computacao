<?php
require_once __DIR__.'/v7-layout.php';cfRequirePermission('timeline.view');
$available=[];foreach(['chamado'=>'sd.view','dispositivo'=>'iot.view','requisicao'=>'crm.view','financeiro'=>'finance.view','sistema'=>'admin.manage'] as $type=>$perm){if(cfCan($perm,$_SESSION))$available[$type]=$perm;}
$type=(string)($_GET['tipo']??array_key_first($available)??'');if(!isset($available[$type])){$type=array_key_first($available)??'';}
$id=trim((string)($_GET['id']??''));if(strlen($id)>64||($id!==''&&!preg_match('/^[A-Za-z0-9_.:-]{1,64}$/',$id)))$id='';
$events=[];$error='';
try{
 $db=cfDb();
 if($type==='chamado' && $id!==''&&ctype_digit($id)){
   $s=$db->prepare('SELECT event_type,actor,note AS summary,created_at FROM sd_ticket_history WHERE ticket_id=? ORDER BY id DESC LIMIT 100');$iid=(int)$id;$s->bind_param('i',$iid);$s->execute();$events=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
 }elseif($type==='dispositivo'){
   $sql="SELECT CONCAT('regra_',e.outcome) AS event_type, 'system:iot' AS actor, CONCAT('Regra #',e.rule_id,': ',e.outcome, IF(e.ticket_id IS NULL,'',CONCAT(' · chamado #',e.ticket_id))) AS summary,e.created_at FROM sd_iot_rule_events e";
   if($id!=='')$sql.=' WHERE e.device_id=?';$sql.=' ORDER BY e.id DESC LIMIT 100';$s=$db->prepare($sql);
   if($id!=='')$s->bind_param('s',$id);$s->execute();$events=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
 }elseif($type==='requisicao' && $id!==''&&ctype_digit($id)){
   $s=$db->prepare('SELECT action AS event_type,actor,note AS summary,created_at FROM crm_activity_v7 WHERE request_id=? ORDER BY id DESC LIMIT 100');$iid=(int)$id;$s->bind_param('i',$iid);$s->execute();$events=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
 }elseif($type!==''){
   $sql='SELECT event_type,actor,summary,created_at FROM cf_operational_events_v7 WHERE entity_type=?';
   if($id!==''){$sql.=' AND entity_id=?';}$sql.=' ORDER BY id DESC LIMIT 100';$s=$db->prepare($sql);
   if($id!=='')$s->bind_param('ss',$type,$id);else $s->bind_param('s',$type);
   $s->execute();$events=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
 }
}catch(Throwable $e){error_log('Histórico V7: '.$e->getMessage());$error='Histórico indisponível. Verifique tabelas e permissões.';}
cfV7Head('Histórico operacional','Eventos e atividades com origem verificável; sem dados de demonstração.');
if($error)cfV7Alert($error,'danger');
?><section class="cf-v7-panel"><form class="cf-v7-toolbar" method="get"><label>Tipo<select name="tipo" class="cf-v7-select"><?php foreach($available as $t=>$perm):?><option value="<?=cfEsc($t)?>" <?=$t===$type?'selected':''?>><?=cfEsc(ucfirst($t))?></option><?php endforeach;?></select></label><label>Identificador (opcional)<input name="id" class="cf-v7-input" maxlength="64" value="<?=cfEsc($id)?>"></label><button class="cf-v7-btn cf-v7-btn-primary">Filtrar eventos</button></form>
<ol class="cf-v7-timeline"><?php foreach($events as $event):?><li><time><?=cfEsc($event['created_at'])?></time><strong><?=cfEsc($event['event_type'])?></strong><span class="cf-v7-muted"><?=cfEsc($event['actor'])?></span><p><?=cfEsc($event['summary']??'')?></p></li><?php endforeach;?><?php if(!$events):?><li>Nenhum evento registrado para os filtros selecionados.</li><?php endif;?></ol></section>
<?php cfV7Foot(); ?>
