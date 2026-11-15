<?php
require_once __DIR__ . '/v5-common.php';
require_once __DIR__ . '/../php/seguranca.php';
require_permissao(['admin', 'gestor']);
$periodo = (int)($_GET['dias'] ?? 30);
if (!in_array($periodo, [7,30,90], true)) $periodo=30;
$indicadores=[];$linhas=[];$erros=[];
try {
 $db=v5db();
 $stmt=$db->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(status='ativo'),0) AS ativos FROM clientes");
 $stmt->execute();$indicadores['clientes']=$stmt->get_result()->fetch_assoc();$stmt->close();
 $stmt=$db->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(status='pendente'),0) AS pendentes, COALESCE(SUM(status='aprovado'),0) AS aprovadas FROM requisicoes WHERE data_requisicao >= DATE_SUB(NOW(), INTERVAL ? DAY)");
 $stmt->bind_param('i',$periodo);$stmt->execute();$indicadores['requisicoes']=$stmt->get_result()->fetch_assoc();$stmt->close();
 $stmt=$db->prepare("SELECT r.id_requisicao, r.status, r.tipo, r.categoria, r.data_requisicao, c.id_cliente, c.nome FROM requisicoes r INNER JOIN clientes c ON c.id_cliente=r.id_cliente WHERE r.data_requisicao >= DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY r.data_requisicao DESC LIMIT 80");
 $stmt->bind_param('i',$periodo);$stmt->execute();$linhas=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
} catch(Throwable $e) {error_log('[CRM-FIN V6] '.$e->getMessage());$erros[]='Dados indisponíveis. Confira a configuração do banco e as tabelas clientes/requisicoes.';}
v5head('Clientes e negócios');
?>
<header class="v5-header"><div><div class="v5-kicker">CONFINTER / Gestão</div><h1>Clientes e negócios</h1><p>Visão integrada e somente de leitura, sem misturar solicitação de crédito com pagamento efetivo.</p></div><nav class="v5-actions"><a class="v5-btn" href="crm/pipeline.php">Pipeline</a><a class="v5-btn" href="financeiro/finance-dashboard.php">Financeiro</a><a class="v5-btn" href="listaclientes.php">Clientes</a></nav></header>
<form method="get" class="v5-panel" aria-label="Filtro do período"><label for="dias">Período de solicitações</label> <select id="dias" name="dias"><?php foreach([7,30,90] as $d):?><option value="<?=$d?>" <?=$periodo===$d?'selected':''?>>Últimos <?=$d?> dias</option><?php endforeach;?></select> <button class="v5-btn v5-primary" type="submit">Aplicar</button></form>
<?php foreach($erros as $erro):?><p class="v5-empty" role="alert"><?=v5e($erro)?></p><?php endforeach;?>
<section class="v5-grid" aria-label="Indicadores"><article class="v5-tile"><span>Clientes cadastrados</span><strong><?=v5e($indicadores['clientes']['total']??'—')?></strong></article><article class="v5-tile"><span>Clientes ativos</span><strong><?=v5e($indicadores['clientes']['ativos']??'—')?></strong></article><article class="v5-tile"><span>Solicitações no período</span><strong><?=v5e($indicadores['requisicoes']['total']??'—')?></strong></article><article class="v5-tile"><span>Solicitações pendentes</span><strong><?=v5e($indicadores['requisicoes']['pendentes']??'—')?></strong></article></section>
<section class="v5-panel"><h2>Solicitações e clientes vinculados</h2><p class="v5-subtle">Este painel NÃO apresenta saldo, recebimento, receita realizada ou confirmação de pagamentos. Solicitações não são transações financeiras.</p><div class="v5-scroll"><table><thead><tr><th>Solicitação</th><th>Cliente</th><th>Categoria</th><th>Tipo</th><th>Status</th><th>Data</th></tr></thead><tbody><?php foreach($linhas as $r):?><tr><td><?=v5e($r['id_requisicao'])?></td><td><a href="crm/ficha-360-v7.php?id=<?=urlencode((string)$r['id_cliente'])?>"><?=v5e($r['nome'])?></a></td><td><?=v5e($r['categoria'])?></td><td><?=v5e($r['tipo'])?></td><td><?=v5e($r['status'])?></td><td><?=v5e($r['data_requisicao'])?></td></tr><?php endforeach;?><?php if(!$linhas):?><tr><td colspan="6">Nenhuma solicitação disponível neste período.</td></tr><?php endif;?></tbody></table></div></section>
<?php v5foot(); ?>
