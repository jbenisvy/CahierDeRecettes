<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header('Location: /auth/login.php');
    exit;
}

function require_role(string $role): void {
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== $role) {
        http_response_code(403);
        echo "Accès interdit";
        exit;
    }
}
function can(string $capability): bool
{
    $role = $_SESSION['user']['role'] ?? null;

    $permissions = [
        'admin' => [
            'manage_users',
            'delete_recette',
            'edit_recette',
            'add_recette',
            'view_recette',
        ],
        'contributeur' => [
            'add_recette',
            'edit_recette',
            'view_recette',
        ],
        'lecteur' => [
            'view_recette',
        ],
    ];

    return isset($permissions[$role]) && in_array($capability, $permissions[$role], true);
}
function require_admin(): void
{
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        http_response_code(403);
        echo "Accès interdit";
        exit;
    }
}
