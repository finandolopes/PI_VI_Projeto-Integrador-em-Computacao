<?php
require_once __DIR__.'/../php/sd_service.php';
function ensure(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$ticket = ['priority'=>'critica','created_at'=>'2026-10-14 10:00:00','first_response_at'=>'2026-10-14 10:10:00','resolved_at'=>'2026-10-14 11:30:00'];
$s = sdSlaStatus($ticket,new DateTimeImmutable('2026-10-14 13:00:00'));
ensure(!$s['response_breached'], 'Resposta no prazo');
ensure(!$s['resolution_breached'], 'Resolução no prazo');
$ticket['resolved_at'] = '2026-10-14 12:30:00';
ensure(sdSlaStatus($ticket)['resolution_breached'], 'Resolução fora do prazo');
$ticket['first_response_at'] = null;
ensure(sdSlaStatus($ticket,new DateTimeImmutable('2026-10-14 10:20:00'))['response_breached'], 'Primeira resposta pendente fora do prazo');
ensure(sdSlaTargets('alta') === [60,480], 'Metas padrão');
print "OK: testes SLA (sem MySQL)\n";
