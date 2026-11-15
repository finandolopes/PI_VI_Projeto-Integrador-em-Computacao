<?php
require_once __DIR__.'/v7_core.php';
/** Livro interno de lançamentos; status rascunho NÃO representa pagamento. */
function cfFinanceValidateDraft(array $p): array {
    $type=(string)($p['entry_type']??'');$description=trim((string)($p['description']??''));
    $rawAmount=(string)($p['amount']??'');
    if(!in_array($type,['receber','pagar'],true)||$description===''||strlen($description)>240)throw new InvalidArgumentException('Tipo ou descrição inválidos.');
    if(!preg_match('/^(?:[0-9]{1,10})(?:\.[0-9]{1,2})?$/D',$rawAmount)||(float)$rawAmount<=0)throw new InvalidArgumentException('Valor monetário inválido. Use ponto decimal.');
    $date=trim((string)($p['due_date']??''));
    $dt=$date!==''?DateTimeImmutable::createFromFormat('!Y-m-d',$date):null;
    if($date!==''&&(!preg_match('/^\d{4}-\d\d-\d\d$/',$date)||!($dt instanceof DateTimeImmutable)||$dt->format('Y-m-d')!==$date))throw new InvalidArgumentException('Data de vencimento inválida.');
    $key=(string)($p['idempotency_key']??'');if(!preg_match('/^[a-f0-9]{64}$/',$key))throw new InvalidArgumentException('Chave de idempotência inválida.');
    $client=filter_var($p['client_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    return ['type'=>$type,'description'=>$description,'amount'=>number_format((float)$rawAmount,2,'.',''),'due'=>$date?:null,'key'=>$key,'client_id'=>$client?:null];
}
function cfFinanceCreateDraft(mysqli $db,array $input,string $actor): int {
    if(!filter_var(getenv('FINANCE_V7_WRITE_ENABLED')?:'0',FILTER_VALIDATE_BOOLEAN))throw new RuntimeException('Lançamentos desabilitados até homologação.');
    $v=cfFinanceValidateDraft($input);
    $db->begin_transaction();
    try{
      if($v['client_id']!==null){$s=$db->prepare('SELECT id_cliente FROM clientes WHERE id_cliente=?');$s->bind_param('i',$v['client_id']);$s->execute();$exists=$s->get_result()->fetch_assoc();$s->close();if(!$exists)throw new InvalidArgumentException('Cliente inexistente.');}
      $s=$db->prepare("INSERT INTO finance_entries_v7(idempotency_key,entry_type,client_id,description,amount,due_date,status,created_by) VALUES(?,?,?,?,?,?,'rascunho',?)");
      $s->bind_param('ssissss',$v['key'],$v['type'],$v['client_id'],$v['description'],$v['amount'],$v['due'],$actor);
      $s->execute();$id=$db->insert_id;$s->close();
      cfRecordEvent($db,'financeiro',(string)$id,'draft_created',$actor,'Rascunho de lançamento interno criado; não é cobrança confirmada');
      $db->commit();return (int)$id;
    }catch(mysqli_sql_exception $e){$db->rollback();if($e->getCode()===1062)throw new DomainException('Solicitação já processada; atualize a listagem.');throw $e;}
    catch(Throwable $e){$db->rollback();throw $e;}
}
