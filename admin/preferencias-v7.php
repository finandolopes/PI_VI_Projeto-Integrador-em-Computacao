<?php
require_once __DIR__.'/v7-layout.php';cfRequirePermission('work.view');
$actor=cfActor();$userKey=substr((string)($_SESSION['user_id']??$_SESSION['usuario_id']??$actor).':'.$actor,0,130);
$density='normal';$favorite='';$error='';$saved=false;
try{$db=cfDb();
 if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
  if(!cfCheckCsrf((string)($_POST['csrf_token']??'')))throw new InvalidArgumentException('Token CSRF inválido');
  $density=(string)($_POST['density']??'normal');$favorite=(string)($_POST['favorite']??'');
  $validFavorites=['','meu-trabalho-v7.php','central-operacoes.php','service-desk.php','crm/pipeline.php','iot-monitoramento.php','financeiro/lancamentos-v7.php'];
  if(!in_array($density,['normal','compacto'],true)||!in_array($favorite,$validFavorites,true))throw new InvalidArgumentException('Preferência inválida.');
  foreach(['density'=>$density,'favorite'=>$favorite] as $key=>$value){$s=$db->prepare('INSERT INTO cf_user_prefs_v7(user_key,preference_key,preference_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE preference_value=VALUES(preference_value)');$s->bind_param('sss',$userKey,$key,$value);$s->execute();$s->close();}
  $saved=true;$_SESSION['cf_v7_density']=$density;
 }
 $s=$db->prepare('SELECT preference_key,preference_value FROM cf_user_prefs_v7 WHERE user_key=?');$s->bind_param('s',$userKey);$s->execute();foreach($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r){if($r['preference_key']==='density')$density=$r['preference_value'];if($r['preference_key']==='favorite')$favorite=$r['preference_value'];}$s->close();
}catch(Throwable $e){error_log('V7 prefs: '.$e->getMessage());$error='Preferências indisponíveis. Confira banco e migrações.';}
cfV7Head('Preferências da interface','Ajustes individuais de densidade e atalho de acesso rápido.');
if($saved)cfV7Alert('Preferências salvas.','success');if($error)cfV7Alert($error,'danger');
?><section class="cf-v7-panel"><form method="post" class="cf-v7-stack"><input type="hidden" name="csrf_token" value="<?=cfEsc(cfCsrfToken())?>"><label>Densidade visual<select class="cf-v7-select" name="density"><option value="normal" <?=$density==='normal'?'selected':''?>>Confortável</option><option value="compacto" <?=$density==='compacto'?'selected':''?>>Compacta</option></select></label><label>Atalho preferido<select class="cf-v7-select" name="favorite"><option value="">Nenhum</option><?php foreach(['meu-trabalho-v7.php'=>'Meu trabalho','central-operacoes.php'=>'Central de operações','service-desk.php'=>'Service Desk','crm/pipeline.php'=>'CRM','iot-monitoramento.php'=>'IoT','financeiro/lancamentos-v7.php'=>'Financeiro'] as $value=>$label):$allowedPerm=str_starts_with($value,'crm/')?'crm.view':(str_starts_with($value,'financeiro/')?'finance.view':(str_starts_with($value,'iot-')?'iot.view':(str_starts_with($value,'service-desk')?'sd.view':'work.view')));if(!cfCan($allowedPerm,$_SESSION))continue;?><option value="<?=cfEsc($value)?>" <?=$favorite===$value?'selected':''?>><?=cfEsc($label)?></option><?php endforeach;?></select></label><button class="cf-v7-btn cf-v7-btn-primary">Salvar preferências</button></form><p class="cf-v7-muted">Os favoritos respeitam suas permissões. O tema institucional e o painel original são preservados.</p></section>
<?php if($favorite!==''):?><section class="cf-v7-panel"><a href="<?=cfEsc($favorite)?>">Abrir meu atalho preferido →</a></section><?php endif;cfV7Foot();?>
