<?php
/** Alocação por intervalo: uma troca de responsável divide o período entre técnicos. */
function sdAssignmentName(?string $raw): string {
 $value=trim((string)$raw);
 $pos=strrpos($value,' | ');
 return $pos===false ? $value : trim(substr($value,$pos+3));
}
function sdCalculateAllocation(array $ticket,array $events,DateTimeImmutable $from,DateTimeImmutable $to): array {
 $opened=new DateTimeImmutable($ticket['created_at']);
 $stop=!empty($ticket['resolved_at'])?min($to->getTimestamp(),(new DateTimeImmutable($ticket['resolved_at']))->getTimestamp()):$to->getTimestamp();
 $start=$opened->getTimestamp();$assignee='';$output=[];
 $segments=[];
 foreach($events as $event){
  $time=(new DateTimeImmutable($event['created_at']))->getTimestamp();
  if($time<$start)continue;
  $segments[]=[$start,$time,$assignee];
  $assignee=sdAssignmentName($event['new_value']??'');
  $start=$time;
 }
 $segments[]=[$start,$stop,$assignee];
 foreach($segments as [$a,$b,$tech]){
  $a=max($a,$from->getTimestamp());$b=min($b,$stop);
  if($b<=$a)continue;
  $name=$tech!==''?$tech:'Não atribuído';
  $output[$name]=($output[$name]??0)+($b-$a)/60;
 }
 return $output;
}
function sdProductivityHistory(mysqli $db,int $days): array {
 $from=new DateTimeImmutable('-'.$days.' days');$to=new DateTimeImmutable();
 $s=$db->prepare('SELECT id,protocol,created_at,resolved_at,assigned_to,status,first_response_at FROM sd_tickets WHERE created_at<=? AND (resolved_at IS NULL OR resolved_at>=?) ORDER BY id DESC LIMIT 10000');
 $fromText=$from->format('Y-m-d H:i:s');$toText=$to->format('Y-m-d H:i:s');
 $s->bind_param('ss',$toText,$fromText);$s->execute();$tickets=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
 $out=[];
 $hist=$db->prepare("SELECT event_type,new_value,created_at,id FROM sd_ticket_history WHERE ticket_id=? ORDER BY created_at,id");
 foreach($tickets as $ticket){
  $id=(int)$ticket['id'];$hist->bind_param('i',$id);$hist->execute();$events=$hist->get_result()->fetch_all(MYSQLI_ASSOC);
  // Histórico 'updated' inclui status e responsável após cada alteração.
  $events=array_values(array_filter($events,static fn($x)=>$x['event_type']==='updated'));
  $alloc=sdCalculateAllocation($ticket,$events,$from,$to);
  foreach($alloc as $name=>$minutes){
   if(!isset($out[$name]))$out[$name]=['responsavel'=>$name,'minutos'=>0,'participacoes'=>0,'chamados'=>[],'conclusoes'=>0];
   $out[$name]['minutos']+=$minutes;$out[$name]['chamados'][$id]=true;
   $out[$name]['participacoes']++;
  }
  if(!empty($ticket['resolved_at'])){
   $resolvedAt=new DateTimeImmutable($ticket['resolved_at']);
   if($resolvedAt>=$from && $resolvedAt<=$to){
    // Crédito de encerramento ao último responsável ativo na resolução, se houver histórico suficiente.
    $name=$events?sdAssignmentName(end($events)['new_value']):trim((string)$ticket['assigned_to']);
    $name=$name!==''?$name:'Não atribuído';
    if(!isset($out[$name]))$out[$name]=['responsavel'=>$name,'minutos'=>0,'participacoes'=>0,'chamados'=>[],'conclusoes'=>0];
    $out[$name]['conclusoes']++;
   }
  }
 }
 $hist->close();
 foreach($out as &$row){$row['chamados']=count($row['chamados']);$row['minutos']=round($row['minutos'],1);}unset($row);
 usort($out,static fn($a,$b)=>$b['minutos']<=>$a['minutos']);return $out;
}
