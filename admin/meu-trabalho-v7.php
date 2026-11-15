<?php
require_once __DIR__.'/v7-layout.php';cfRequirePermission('work.view');
$stats=[];$shortcuts=[];$error='';$actor=cfActor();$favorite='';
try{
 $db=cfDb();
 $prefKey=substr((string)($_SESSION['user_id']??$_SESSION['usuario_id']??$actor).':'.$actor,0,130);
 $pr=$db->prepare("SELECT preference_value FROM cf_user_prefs_v7 WHERE user_key=? AND preference_key='favorite'");$pr->bind_param('s',$prefKey);$pr->execute();$favorite=(string)($pr->get_result()->fetch_assoc()['preference_value']??'');$pr->close();
 if(cfCan('sd.view',$_SESSION)){
  $s=$db->prepare("SELECT COUNT(*) n FROM sd_tickets WHERE assigned_to=? AND status NOT IN ('resolvido','fechado')");$s->bind_param('s',$actor);$s->execute();$stats[]=['Meus chamados ativos',(int)$s->get_result()->fetch_assoc()['n'],'service-desk.php?responsavel='.rawurlencode($actor)];$s->close();
  $stats[]=['Alertas operacionais',(int)$db->query('SELECT COUNT(*) n FROM sd_notifications WHERE resolved_at IS NULL AND acknowledged_at IS NULL')->fetch_assoc()['n'],'service-desk-notificacoes.php'];
  $shortcuts[]=['Chamados','service-desk.php'];$shortcuts[]=['Kanban','service-desk-kanban.php'];$shortcuts[]=['Dispositivos IoT','iot-monitoramento.php'];
 }
 if(cfCan('crm.view',$_SESSION)){
  $stats[]=['Solicitações pendentes',(int)$db->query("SELECT COUNT(*) n FROM requisicoes WHERE status='pendente'")->fetch_assoc()['n'],'requisicoes.php'];
  $shortcuts[]=['Clientes','listaclientes.php'];$shortcuts[]=['Pipeline CRM','crm/pipeline.php'];$shortcuts[]=['Ficha do cliente','crm/ficha-360-v7.php'];
 }
 if(cfCan('finance.view',$_SESSION)){
  $stats[]=['Rascunhos financeiros',(int)$db->query("SELECT COUNT(*) n FROM finance_entries_v7 WHERE status='rascunho'")->fetch_assoc()['n'],'financeiro/lancamentos-v7.php'];
  $shortcuts[]=['Livro financeiro interno','financeiro/lancamentos-v7.php'];
 }
}catch(Throwable $e){error_log('V7 meu trabalho: '.$e->getMessage());$error='Dados temporariamente indisponíveis. Verifique MySQL e migrações V7.';}
cfV7Head('Meu trabalho','Indicadores e atalhos ajustados às permissões do seu perfil.');
if($error)cfV7Alert($error,'danger');
?><section class="cf-v7-grid" aria-label="Indicadores disponíveis"><?php foreach($stats as [$title,$count,$link])cfV7Stat($title,(string)$count,$link);?></section>
<div class="cf-v7-toolbar"><a class="cf-v7-btn" href="preferencias-v7.php">Personalizar atalhos e densidade</a><?php if($favorite!==''):?><a class="cf-v7-btn cf-v7-btn-primary" href="<?=cfEsc($favorite)?>">Abrir favorito</a><?php endif;?></div><section class="cf-v7-columns"><article class="cf-v7-panel"><h2>Atalhos de operação</h2><div class="cf-v7-boards"><?php foreach($shortcuts as [$title,$link]):?><div class="cf-v7-board"><a href="<?=cfEsc($link)?>"><?=cfEsc($title)?> →</a></div><?php endforeach;?></div><?php if(!$shortcuts):?><p class="cf-v7-muted">Nenhum módulo operacional autorizado para o perfil atual.</p><?php endif;?></article><article class="cf-v7-panel"><h2>Contexto da sessão</h2><p><strong>Usuário:</strong> <?=cfEsc($actor)?></p><p><strong>Perfis:</strong> <?=cfEsc(implode(', ',cfNormalizedRoles($_SESSION)))?></p><p class="cf-v7-muted">Indicadores usam o banco atual, não valores fictícios. A ausência de dados é apresentada como indisponibilidade, nunca como zero.</p></article></section>
<?php cfV7Foot(); ?>
