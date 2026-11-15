<?php
require_once __DIR__.'/v7-layout.php';cfRequirePermission('work.view');
$q=trim((string)($_GET['q']??''));$result=[];$error='';
if(strlen($q)>90)$q=substr($q,0,90);
if($q!==''){
 try {
  $db=cfDb();$like='%'.$q.'%';
  if(cfCan('sd.view',$_SESSION)){
   $s=$db->prepare('SELECT id,protocol,title,status FROM sd_tickets WHERE protocol LIKE ? OR title LIKE ? ORDER BY id DESC LIMIT 20');
   $s->bind_param('ss',$like,$like);$s->execute();foreach($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r)$result[]=['label'=>$r['protocol'].' · '.$r['title'],'context'=>'Chamado · '.$r['status'],'url'=>'service-desk-detalhe.php?id='.$r['id']];$s->close();
  }
  if(cfCan('iot.view',$_SESSION)){
   $s=$db->prepare('SELECT id,device_id,name,location FROM iot_devices WHERE device_id LIKE ? OR name LIKE ? OR location LIKE ? ORDER BY last_seen DESC LIMIT 20');
   $s->bind_param('sss',$like,$like,$like);$s->execute();foreach($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r)$result[]=['label'=>$r['device_id'].' · '.$r['name'],'context'=>'Dispositivo · '.$r['location'],'url'=>'iot-dispositivo.php?device='.rawurlencode($r['device_id'])];$s->close();
  }
  if(cfCan('crm.view',$_SESSION)){
   $s=$db->prepare('SELECT id_cliente,nome,status FROM clientes WHERE nome LIKE ? ORDER BY id_cliente DESC LIMIT 20');
   $s->bind_param('s',$like);$s->execute();foreach($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r)$result[]=['label'=>$r['nome'],'context'=>'Cliente · '.$r['status'],'url'=>'crm/ficha-360-v7.php?id='.$r['id_cliente']];$s->close();
   if(ctype_digit($q)){$rid=(int)$q;$s=$db->prepare('SELECT id_requisicao,status,tipo FROM requisicoes WHERE id_requisicao=? LIMIT 1');$s->bind_param('i',$rid);$s->execute();if($r=$s->get_result()->fetch_assoc())$result[]=['label'=>'Solicitação #'.$r['id_requisicao'].' · '.$r['tipo'],'context'=>'Requisição · '.$r['status'],'url'=>'requisicoes.php'];$s->close();}
  }
 }catch(Throwable $e){error_log('V7 search: '.$e->getMessage());$error='Não foi possível pesquisar no banco. Confira as tabelas e a conexão.';}
}
cfV7Head('Pesquisa global','Resultados restritos às informações autorizadas para seu perfil.');
if($error)cfV7Alert($error,'danger');
?><section class="cf-v7-panel"><form method="get" role="search" class="cf-v7-toolbar"><label for="v7-search-q">Pesquisar clientes, chamados, protocolos e dispositivos</label><input class="cf-v7-input" id="v7-search-q" name="q" value="<?=cfEsc($q)?>" maxlength="90" placeholder="Nome, protocolo ou dispositivo" required><button class="cf-v7-btn cf-v7-btn-primary">Pesquisar</button></form></section>
<section class="cf-v7-panel"><h2>Resultados <?=($q!==''?'('.count($result).')':'')?></h2><div class="cf-v7-stack"><?php foreach($result as $r):?><a class="cf-v7-btn" style="justify-content:space-between;gap:12px;text-align:left" href="<?=cfEsc($r['url'])?>"><span><strong><?=cfEsc($r['label'])?></strong><small style="display:block" class="cf-v7-muted"><?=cfEsc($r['context'])?></small></span><span aria-hidden="true">→</span></a><?php endforeach;?><?php if(!$result):?><p class="cf-v7-muted"><?=$q!==''?'Nenhum registro encontrado com esse termo.':'Informe um termo para iniciar a pesquisa.'?></p><?php endif;?></div></section>
<?php cfV7Foot(); ?>
