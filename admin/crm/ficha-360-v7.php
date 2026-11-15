<?php
require_once __DIR__.'/../v7-layout.php';cfRequirePermission('crm.view');
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$client=null;$requests=[];$activities=[];$error='';
try{
 $db=cfDb();
 if($id){$s=$db->prepare('SELECT id_cliente,nome,email,telefone,status,data_cadastro FROM clientes WHERE id_cliente=?');$s->bind_param('i',$id);$s->execute();$client=$s->get_result()->fetch_assoc();$s->close();
    if($client){$s=$db->prepare('SELECT id_requisicao,status,tipo,categoria,data_requisicao,valor_solicitado FROM requisicoes WHERE id_cliente=? ORDER BY id_requisicao DESC LIMIT 60');$s->bind_param('i',$id);$s->execute();$requests=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
    $s=$db->prepare('SELECT action,previous_status,current_status,actor,note,created_at FROM crm_activity_v7 WHERE client_id=? ORDER BY id DESC LIMIT 40');$s->bind_param('i',$id);$s->execute();$activities=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();}}
}catch(Throwable $e){error_log('CRM ficha V7: '.$e->getMessage());$error='Não foi possível consultar o banco. Verifique as migrações.';}
cfV7Head('Cliente 360°','Solicitações e atividades relacionadas pelo identificador do cliente, sem vinculações por nome ou e-mail.');
if($error)cfV7Alert($error,'danger');
if(!$id):?><section class="cf-v7-panel"><h2>Consultar um cliente</h2><form method="get" class="cf-v7-toolbar"><label for="id">ID do cliente</label><input class="cf-v7-input" id="id" name="id" type="number" min="1" required><button class="cf-v7-btn cf-v7-btn-primary">Abrir ficha</button></form><a href="../listaclientes.php">Consultar clientes cadastrados</a></section><?php
elseif(!$client):cfV7Alert('Cliente não encontrado, ou banco indisponível.','danger');
else:?>
<section class="cf-v7-grid"><?php cfV7Stat('Cliente','#'.(string)$client['id_cliente']);cfV7Stat('Status',(string)$client['status']);cfV7Stat('Solicitações',count($requests));cfV7Stat('Interações recentes',count($activities));?></section>
<section class="cf-v7-columns"><article class="cf-v7-panel"><h2>Dados cadastrais</h2><p><strong>Nome:</strong> <?=cfEsc($client['nome'])?></p><p><strong>E-mail:</strong> <?=cfEsc($client['email'])?></p><p><strong>Telefone:</strong> <?=cfEsc($client['telefone'])?></p><p class="cf-v7-muted">Dados restritos a perfis comerciais autorizados. Não são exibidos CPF, RG ou endereço nesta ficha de resumo.</p><h2>Solicitações</h2><div class="cf-v7-tablewrap"><table><thead><tr><th>ID</th><th>Tipo</th><th>Status</th><th>Data</th></tr></thead><tbody><?php foreach($requests as $r):?><tr><td><?=cfEsc($r['id_requisicao'])?></td><td><?=cfEsc($r['tipo'])?></td><td><span class="cf-v7-chip"><?=cfEsc($r['status'])?></span></td><td><?=cfEsc($r['data_requisicao'])?></td></tr><?php endforeach;?><?php if(!$requests):?><tr><td colspan="4">Sem solicitações vinculadas</td></tr><?php endif;?></tbody></table></div></article><article class="cf-v7-panel"><h2>Histórico de atividades CRM</h2><ol class="cf-v7-timeline"><?php foreach($activities as $event):?><li><time><?=cfEsc($event['created_at'])?></time><strong><?=cfEsc($event['action'])?></strong><span><?=cfEsc($event['previous_status'])?> → <?=cfEsc($event['current_status'])?></span><p class="cf-v7-muted"><?=cfEsc($event['actor'])?> · <?=cfEsc($event['note'])?></p></li><?php endforeach;?><?php if(!$activities):?><li>Sem atividades V7 registradas.</li><?php endif;?></ol></article></section>
<?php endif;cfV7Foot(); ?>
