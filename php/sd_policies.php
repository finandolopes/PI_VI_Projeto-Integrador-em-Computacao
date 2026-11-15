<?php
/** Regras puras, reutilizáveis e testáveis do Service Desk. */
function sdValidateTransition(string $from, string $to, string $note): void {
    $allowed = [
        'aberto'=>['aberto','em_atendimento','aguardando','resolvido'],
        'em_atendimento'=>['em_atendimento','aguardando','resolvido'],
        'aguardando'=>['aguardando','em_atendimento','resolvido'],
        'resolvido'=>['resolvido','em_atendimento','fechado'],
        'fechado'=>['fechado','em_atendimento'],
    ];
    if (!isset($allowed[$from]) || !in_array($to,$allowed[$from],true)) {
        throw new InvalidArgumentException('Transição de status inválida.');
    }
    if ($from !== $to && in_array($to,['resolvido','fechado'],true) && strlen(trim($note)) < 10) {
        throw new InvalidArgumentException('Descreva a solução antes de resolver ou encerrar (mínimo de 10 caracteres).');
    }
    if ($from === 'fechado' && $to === 'em_atendimento' && strlen(trim($note)) < 10) {
        throw new InvalidArgumentException('Informe a justificativa para reabrir um chamado fechado.');
    }
}

/** Proteção contra fórmulas em clientes de planilha que abrem CSV exportado. */
function sdSafeCsvValue(mixed $value): string {
    $text=(string)($value ?? '');
    $text=str_replace(["\r\n","\r"],"\n",$text);
    if (preg_match('/^[\x00-\x20\x{00A0}]*[=+\-@\t\r]/u',$text)) return "'".$text;
    return $text;
}

/** Impede exclusão da própria conta mesmo quando o login utiliza IDs legados. */
function sdIsSelfDelete(array $session, int $id): bool {
    if ($id <= 0) return false;
    foreach (['user_id','usuario_id','id_usuario'] as $key) {
        if (isset($session[$key]) && (int)$session[$key] === $id) return true;
    }
    return false;
}
