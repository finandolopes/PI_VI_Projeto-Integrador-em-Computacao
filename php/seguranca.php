<?php
/**
 * Camada de segurança compatível com módulos avançados.
 */

declare(strict_types=1);
require_once __DIR__ . '/access_policy_v7.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!function_exists('normalizarSessaoUsuario')) {
    function normalizarSessaoUsuario(): void
    {
        if (empty($_SESSION['username']) && !empty($_SESSION['usuario'])) {
            $_SESSION['username'] = (string) $_SESSION['usuario'];
        }
        if (empty($_SESSION['user_id']) && !empty($_SESSION['usuario_id'])) {
            $_SESSION['user_id'] = (int) $_SESSION['usuario_id'];
        }
    }
}

if (!function_exists('basePathFromAdmin')) {
    function basePathFromAdmin(): string
    {
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $parts = explode('/admin/', $script, 2);
        return $parts[0] ?? '';
    }
}

if (!function_exists('isApiRequestCompat')) {
    function isApiRequestCompat(): bool
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if (stripos($uri, '/api/') !== false) {
            return true;
        }

        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        return stripos($accept, 'application/json') !== false;
    }
}

if (!function_exists('forbiddenResponseCompat')) {
    function forbiddenResponseCompat(string $message, int $statusCode): void
    {
        http_response_code($statusCode);

        if (isApiRequestCompat()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $_SESSION['login_error'] = $message;
        header('Location: ' . basePathFromAdmin() . '/index.php');
        exit();
    }
}

if (!function_exists('verificarSessao')) {
    function verificarSessao(): void
    {
        normalizarSessaoUsuario();

        $hasUser = !empty($_SESSION['username']) || !empty($_SESSION['user_id']);
        if (!$hasUser) {
            forbiddenResponseCompat('Faça login para acessar o painel administrativo.', 401);
        }
    }
}

if (!function_exists('require_permissao')) {
    /**
     * @param array<int,string> $allowedRoles
     */
    function require_permissao(array $allowedRoles): void
    {
        verificarSessao();

        $normalizedAllowed=[];
        foreach($allowedRoles as $allowedRole){
            $normalizedAllowed=array_merge($normalizedAllowed,cfNormalizedRoles(['perfil'=>(string)$allowedRole]));
        }
        if(!array_intersect(array_unique($normalizedAllowed),cfNormalizedRoles($_SESSION))){
            forbiddenResponseCompat('Usuário sem permissão para acessar este recurso.',403);
        }
    }
}

