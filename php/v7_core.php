<?php
require_once __DIR__.'/bootstrap_env.php';
require_once __DIR__.'/access_policy_v7.php';
function cfDb(): mysqli {
    require __DIR__.'/conexao.php';
    if(!($conexao instanceof mysqli))throw new RuntimeException('Banco indisponível: execute migrações e configure DB_* com modo demo desligado.');
    return $conexao;
}
function cfEsc($value): string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function cfRecordEvent(mysqli $db,string $type,string $entityId,string $action,string $actor,string $summary): void {
    $stmt=$db->prepare('INSERT INTO cf_operational_events_v7(entity_type,entity_id,event_type,actor,summary) VALUES(?,?,?,?,?)');
    $stmt->bind_param('sssss',$type,$entityId,$action,$actor,$summary);$stmt->execute();$stmt->close();
}
function cfActor(): string {
    if(session_status()!==PHP_SESSION_ACTIVE)session_start();
    return trim((string)($_SESSION['username']??$_SESSION['usuario']??'operador'));
}
function cfHasTable(mysqli $db,string $name): bool {
    if(!preg_match('/^[a-zA-Z0-9_]+$/',$name))return false;
    $s=$db->prepare('SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $s->bind_param('s',$name);$s->execute();$ok=(int)$s->get_result()->fetch_assoc()['n']>0;$s->close();return $ok;
}
function cfEngine(mysqli $db,string $name): string {
    $s=$db->prepare('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $s->bind_param('s',$name);$s->execute();$row=$s->get_result()->fetch_assoc();$s->close();return strtolower((string)($row['ENGINE']??''));
}
