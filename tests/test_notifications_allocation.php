<?php
require_once __DIR__.'/../php/sd_productivity_history.php';
function verify(bool $condition,string $message): void {if(!$condition)throw new RuntimeException($message);}
$ticket=['created_at'=>'2026-10-18 10:00:00','resolved_at'=>'2026-10-18 11:00:00'];
$events=[['created_at'=>'2026-10-18 10:10:00','new_value'=>'em_atendimento | Ana'],['created_at'=>'2026-10-18 10:30:00','new_value'=>'em_atendimento | Bruno']];
$minutes=sdCalculateAllocation($ticket,$events,new DateTimeImmutable('2026-10-18 09:00:00'),new DateTimeImmutable('2026-10-18 12:00:00'));
verify(abs($minutes['Não atribuído']-10)<0.01,'Unassigned incorrect');
verify(abs($minutes['Ana']-20)<0.01,'Ana incorrect');
verify(abs($minutes['Bruno']-30)<0.01,'Bruno incorrect');
echo "OK: alocação histórica proporcional validada\n";
