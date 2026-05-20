<?php
require_once __DIR__ . '/../../app/base_url.php';

if (!function_exists('redirect')) {
    function redirect(string $path): void
    {
        $path = ltrim($path, '/');
        header('Location: ' . PUBLIC_URL . '/' . $path);
        exit;
    }
}

if (!function_exists('require_login')) {
    function require_login(): void
    {
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . '/?action=login');
            exit;
        }
    }
}

if (!function_exists('login_user')) {
    function login_user(array $user, ?PDO $pdo = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => (int) ($user['id'] ?? 0),
            'nom' => (string) ($user['nom'] ?? ''),
            'role' => (string) ($user['role'] ?? 'lecteur'),
        ];

        if ($pdo instanceof PDO && !empty($user['id'])) {
            $stmt = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
            $stmt->execute([(int) $user['id']]);
        }
    }
}

if (!function_exists('require_admin')) {
    function require_admin(): void
    {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            http_response_code(403);
            echo "Accès interdit";
            exit;
        }
    }
}

if (!function_exists('can')) {
    function can(string $capability): bool
    {
        $role = $_SESSION['user']['role'] ?? null;

        $permissions = [
            'admin' => ['manage_users', 'delete_recette', 'edit_recette', 'add_recette', 'view_recette'],
            'contributeur' => ['add_recette', 'edit_recette', 'view_recette'],
            'lecteur' => ['view_recette'],
        ];

        return isset($permissions[$role]) && in_array($capability, $permissions[$role], true);
    }
}

if (!function_exists('require_capability')) {
    function require_capability(string $capability): void
    {
        require_login();
        if (!can($capability)) {
            http_response_code(403);
            echo "Accès interdit";
            exit;
        }
    }
}
