<?php
require_once __DIR__.'/v7-layout.php';require_once __DIR__.'/../php/observability_v7.php';cfRequirePermission('admin.manage');
$db=null;$error='';$checks=[];$jobs=[];$errors=[];
$db=cfHealthConnection();if(!$db){$error='Conexão indisponível ou banco não migrado.';}
$checks=cfHealthSnapshot($db);
if($db){try{
 if(cfHasTable($db,'cf_job_runs_v7'))$jobs=$db->query('SELECT job_name,outcome,duration_ms,summary,created_at FROM cf_job_runs_v7 ORDER BY id DESC LIMIT 25')->fetch_all(MYSQLI_ASSOC);
 if(cfHasTable($db,'cf_api_errors_v7'))$errors=$db->query('SELECT component,category,COUNT(*) qty FROM cf_api_errors_v7 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY component,category')->fetch_all(MYSQLI_ASSOC);
}catch(Throwable $e){error_log('V7 diag: '.$e->getMessage());$error='Não foi possível ler todos os registros operacionais.';}}
cfV7Head('Diagnóstico e observabilidade','Condições técnicas e rastreabilidade operacional; nenhum segredo é exibido.');
if($error)cfV7Alert($error,'danger');
?><section class="cf-v7-grid"><?php cfV7Stat('Verificações',count($checks));cfV7Stat('Aprovadas',count(array_filter($checks,fn($x)=>$x['ok'])));cfV7Stat('Alertas',count(array_filter($checks,fn($x)=>!$x['ok'])));cfV7Stat('Falhas API (24h)',array_sum(array_column($errors,'qty')));?></section>
<div class="cf-v7-columns"><section class="cf-v7-panel"><h2>Diagnóstico de requisitos</h2><div class="cf-v7-tablewrap"><table><thead><tr><th>Recurso</th><th>Situação</th><th>Detalhe</th></tr></thead><tbody><?php foreach($checks as $c):?><tr><td><?=cfEsc($c['name'])?></td><td><span class="cf-v7-chip"><?=($c['ok']?'OK':'Atenção')?></span></td><td><?=cfEsc($c['detail'])?></td></tr><?php endforeach;?></tbody></table></div></section><section class="cf-v7-panel"><h2>Últimas execuções agendadas</h2><div class="cf-v7-tablewrap"><table><thead><tr><th>Tarefa</th><th>Status</th><th>Executado em</th></tr></thead><tbody><?php foreach($jobs as $j):?><tr><td><?=cfEsc($j['job_name'])?></td><td><?=cfEsc($j['outcome'])?></td><td><?=cfEsc($j['created_at'])?></td></tr><?php endforeach;?><?php if(!$jobs):?><tr><td colspan="3">Sem dados: configure o cron V7.</td></tr><?php endif;?></tbody></table></div><p class="cf-v7-muted">Registros de erros da API são agrupados por categoria, sem salvar payloads, credenciais ou dados de clientes.</p><?php foreach($errors as $e):?><p><?=cfEsc($e['component'])?> · <?=cfEsc($e['category'])?>: <strong><?=cfEsc($e['qty'])?></strong></p><?php endforeach;?></section></div>
<?php cfV7Foot(); ?>
