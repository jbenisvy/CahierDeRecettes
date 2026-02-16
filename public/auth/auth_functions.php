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
