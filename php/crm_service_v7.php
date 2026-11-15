<?php
require_once __DIR__.'/v7_core.php';
function cfCrmAllowedTransitions(): array {return ['pendente'=>['em_analise','cancelado'],'em_analise'=>['pendente','aprovado','reprovado','cancelado'],'aprovado'=>[],'reprovado'=>[],'cancelado'=>[]];}
function cfCrmTransitionAllowed(string $before,string $after): bool {
    return in_array($after,cfCrmAllowedTransitions()[$before]??[],true);
}
function cfCrmPipeline(mysqli $db,int $limit=120): array {
    $limit=max(1,min(200,$limit));
    $s=$db->prepare('SELECT r.id_requisicao, r.id_cliente, r.status, r.tipo, r.categoria, r.data_requisicao, c.nome FROM requisicoes r INNER JOIN clientes c ON c.id_cliente=r.id_cliente ORDER BY r.id_requisicao DESC LIMIT ?');
    $s->bind_param('i',$limit);$s->execute();$rows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();return $rows;
}
function cfCrmDetail(mysqli $db,int $id): ?array {
    $s=$db->prepare('SELECT r.id_requisicao,r.id_cliente,r.status,r.tipo,r.categoria,r.data_requisicao,r.observacoes,c.nome,c.email FROM requisicoes r INNER JOIN clientes c ON c.id_cliente=r.id_cliente WHERE r.id_requisicao=?');
    $s->bind_param('i',$id);$s->execute();$row=$s->get_result()->fetch_assoc();$s->close();return $row?:null;
}
/** Esquema legado MyISAM não aceita transação; bloquear mutação até conversão segura. */
function cfCrmSetStage(mysqli $db,int $id,string $newStatus,string $note,string $actor): array {
    if(!filter_var(getenv('CRM_V7_WRITE_ENABLED')?:'0',FILTER_VALIDATE_BOOLEAN))throw new RuntimeException('Alterações CRM desabilitadas até homologação.');
    if(cfEngine($db,'requisicoes')!=='innodb'||cfEngine($db,'clientes')!=='innodb')throw new RuntimeException('Conversão de clientes/requisicoes para InnoDB necessária antes de alterações transacionais.');
    if($id<1||strlen($note)>1000)throw new InvalidArgumentException('Dados inválidos.');
    $db->begin_transaction();
    try {
        $s=$db->prepare('SELECT id_cliente,status FROM requisicoes WHERE id_requisicao=? FOR UPDATE');$s->bind_param('i',$id);$s->execute();$old=$s->get_result()->fetch_assoc();$s->close();
        if(!$old)throw new InvalidArgumentException('Solicitação não encontrada.');
        if(!cfCrmTransitionAllowed((string)$old['status'],$newStatus))throw new InvalidArgumentException('Transição de status não permitida.');
        $s=$db->prepare('UPDATE requisicoes SET status=? WHERE id_requisicao=?');$s->bind_param('si',$newStatus,$id);$s->execute();$s->close();
        $clientId=(int)$old['id_cliente'];$previous=(string)$old['status'];
        $s=$db->prepare("INSERT INTO crm_activity_v7(client_id,request_id,action,previous_status,current_status,actor,note) VALUES(?,?,'status_changed',?,?,?,?)");
        $s->bind_param('iissss',$clientId,$id,$previous,$newStatus,$actor,$note);$s->execute();$s->close();
        cfRecordEvent($db,'requisicao',(string)$id,'status_changed',$actor,'Status '.$previous.' → '.$newStatus);
        $db->commit();return ['id'=>$id,'previous_status'=>$previous,'status'=>$newStatus];
    } catch(Throwable $e){$db->rollback();throw $e;}
}
