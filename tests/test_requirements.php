<?php
require_once __DIR__.'/../php/sd_service.php';
function checkIt(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message); echo "OK: $message\n";}
$now=new DateTimeImmutable('2026-10-14 12:00:00');
$base=['created_at'=>'2026-10-14 09:00:00','priority'=>'critica','status'=>'aberto','origin'=>'iot','first_response_at'=>null,'resolved_at'=>null];
$a=sdSlaStatus($base,$now);
checkIt($a['response_breached'] && $a['resolution_breached'],'SLA crítico vencido');
checkIt($a['response_minutes_target']>0,'meta de primeira resposta');
$closed=$base;$closed['first_response_at']='2026-10-14 09:05:00';$closed['resolved_at']='2026-10-14 09:30:00';$closed['status']='fechado';
$b=sdSlaStatus($closed,$now);
checkIt(!$b['response_breached'] && !$b['resolution_breached'],'SLA respeitado em ticket fechado');
$summary=sdSlaSummary([$base,$closed],$now);
checkIt($summary['total']===2 && $summary['pending']===1 && $summary['completed']===1,'agregação de estados');
checkIt($summary['overdue_response']===1 && $summary['overdue_resolution']===1,'contagem de violações');
checkIt($summary['compliance_percent']===50.0,'percentual de cumprimento');
