<?php
/** RBAC unificado para novos módulos; princípio do menor privilégio. */
function cfNormalizedRoles(array $session): array {
    $roles=[];
    foreach(['perfil','role'] as $key){
        $v=strtolower(trim((string)($session[$key]??'')));
        $aliases=['administrador'=>'admin','desenvolvedor'=>'admin','developer'=>'admin','gerente'=>'gestor','manager'=>'gestor','técnico'=>'tecnico','technician'=>'tecnico','support'=>'tecnico','suporte'=>'tecnico','analyst'=>'analista','finance'=>'financeiro','comercial'=>'comercial'];
        if($v!=='')$roles[]=$aliases[$v]??$v;
    }
    if(!empty($session['is_admin']))$roles[]='admin';
    return array_values(array_unique($roles));
}
function cfAuthenticated(array $session): bool {
    return !empty($session['username'])||!empty($session['usuario'])||!empty($session['user_id'])||!empty($session['usuario_id']);
}
function cfCan(string $permission, array $session): bool {
    if(!cfAuthenticated($session))return false;
    $matrix=[
      'admin'=>['*'],
      'gestor'=>['sd.view','sd.manage','iot.view','crm.view','crm.manage','finance.view','finance.manage','reports.view','work.view','timeline.view'],
      'tecnico'=>['sd.view','sd.manage','iot.view','work.view','timeline.view'],
      'analista'=>['sd.view','iot.view','crm.view','reports.view','work.view','timeline.view'],
      'comercial'=>['crm.view','crm.manage','work.view','timeline.view'],
      'financeiro'=>['finance.view','finance.manage','reports.view','work.view','timeline.view'],
      'usuario'=>['work.view']
    ];
    foreach(cfNormalizedRoles($session) as $role)if(in_array('*',$matrix[$role]??[],true)||in_array($permission,$matrix[$role]??[],true))return true;
    return false;
}
function cfRequirePermission(string $permission): void {
    if(session_status()!==PHP_SESSION_ACTIVE)session_start();
    if(!cfAuthenticated($_SESSION)){
        if(str_contains((string)($_SERVER['REQUEST_URI']??''),'/api/')){http_response_code(401);header('Content-Type: application/json');echo json_encode(['ok'=>false,'error'=>'Autenticação necessária']);}
        else {$prefix=explode('/admin/',(string)($_SERVER['SCRIPT_NAME']??''),2)[0];header('Location: '.$prefix.'/index.php');}
        exit;
    }
    if(!cfCan($permission,$_SESSION)){
        http_response_code(403);
        if(str_contains((string)($_SERVER['REQUEST_URI']??''),'/api/')){header('Content-Type: application/json');echo json_encode(['ok'=>false,'error'=>'Permissão insuficiente']);}
        else {header('Content-Type: text/html; charset=UTF-8');echo '<!doctype html><html lang="pt-br"><meta charset="utf-8"><title>Acesso restrito</title><main><h1>Acesso restrito</h1><p>Você não possui permissão para este módulo.</p></main></html>';}
        exit;
    }
}
function cfCsrfToken(): string {
    if(session_status()!==PHP_SESSION_ACTIVE)session_start();
    if(empty($_SESSION['csrf_token']))$_SESSION['csrf_token']=bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function cfCheckCsrf(string $token): bool {
    if(session_status()!==PHP_SESSION_ACTIVE)session_start();
    return $token!=='' && !empty($_SESSION['csrf_token']) && hash_equals((string)$_SESSION['csrf_token'],$token);
}
