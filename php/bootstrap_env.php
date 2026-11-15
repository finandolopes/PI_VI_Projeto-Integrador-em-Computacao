<?php
/** Leitor minimalista de .env para PHP sem Composer; nunca sobrescreve variáveis reais. */
function cfBootstrapEnv(?string $file = null): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $file ??= dirname(__DIR__) . '/.env';
    if (!is_file($file) || !is_readable($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !preg_match('/^([A-Z][A-Z0-9_]*)\s*=\s*(.*)$/', $line, $m)) continue;
        $key=$m[1];$value=trim($m[2]);
        if (strlen($value)>=2 && ($value[0] === '"' || $value[0] === "'") && substr($value,-1)===$value[0]) $value=substr($value,1,-1);
        if (getenv($key) === false) putenv($key.'='.$value);
    }
}
cfBootstrapEnv();
$cfTz=getenv('TIMEZONE')?:'America/Sao_Paulo';
try{date_default_timezone_set((new DateTimeZone($cfTz))->getName());}catch(Throwable $e){date_default_timezone_set('America/Sao_Paulo');}
