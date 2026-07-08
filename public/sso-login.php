<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../app/base_url.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth/auth_functions.php';
require_once __DIR__ . '/../app/services/SsoService.php';

$redirectToLogin = static function (): never {
    header('Location: ' . BASE_URL . '/?action=login&sso_error=1');
    exit;
};

$config = require __DIR__ . '/../config/sso.php';
$pdo = getPDO();
$token = trim((string) ($_GET['token'] ?? ''));

if ($token === '') {
    error_log('[sso] missing token');
    $redirectToLogin();
}

try {
    $service = new SsoService($pdo, $config);
    $user = $service->consumeToken($token);
    login_user($user, $pdo);
    header('Location: ' . BASE_URL . '/');
    exit;
} catch (Throwable $e) {
    error_log('[sso] ' . $e->getMessage());
    $redirectToLogin();
}
