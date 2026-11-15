<?php
/** Adapter da API legada de autorização para a matriz de permissões V7. */
require_once __DIR__.'/access_policy_v7.php';
function sdRequireRole(string $minimum='technician'): void {
    cfRequirePermission($minimum==='admin'?'admin.manage':'sd.view');
}
