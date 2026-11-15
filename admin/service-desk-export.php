<?php
session_start();
require_once __DIR__.'/../php/sd_authorization.php';
sdRequireRole('technician');
require_once __DIR__.'/../php/conexao.php';
require_once __DIR__.'/../php/sd_service.php';
sdSchema($conexao);
$allowed=['todos','manual','iot'];
$origin=(string)($_GET['origin']??'todos');
if(!in_array($origin,$allowed,true)){$origin='todos';}
$sql="SELECT protocol,origin,category,priority,status,assigned_to,device_id,created_at,first_response_at,resolved_at FROM sd_tickets";
if($origin!=='todos')$sql.=" WHERE origin=?";
$sql.=" ORDER BY created_at DESC LIMIT 10000";
$st=$conexao->prepare($sql);
if($origin!=='todos')$st->bind_param('s',$origin);
$st->execute();$results=$st->get_result();
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="service_desk_relatorio.csv"');
header('Cache-Control: no-store');
$stream=fopen('php://output','w');
fwrite($stream,"\xEF\xBB\xBF");
fputcsv($stream,['Protocolo','Origem','Categoria','Prioridade','Status','Responsável','Dispositivo','Abertura','Primeira resposta','Resolução'],';');
while($row=$results->fetch_assoc()){
  $safe=array_map('sdSafeCsvValue',array_values($row));
  fputcsv($stream,$safe,';');
}
fclose($stream);$st->close();
