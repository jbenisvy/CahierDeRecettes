<?php
declare(strict_types=1);

// ===============================
// AUTH — BOÎTE À OUTILS UNIQUEMENT
// ===============================

require_once __DIR__ . '/../../app/base_url.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Redirection centrale (TOUJOURS via public/)
 */
function redirect(string $path): void
{
    $path = ltrim($path, '/');
    header('Location: ' . PUBLIC_URL . '/' . $path);
    exit;
}

/**
 * Vérifie si l'utilisateur est connecté
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Retourne l'utilisateur courant ou null
 */
function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Force la connexion
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirect('index.php?action=login');
    }
}

/**
 * Vérifie un rôle précis
 */
function requireRole(string $role): void
{
    requireLogin();

    if (($_SESSION['user']['role'] ?? null) !== $role) {
        http_response_code(403);
        echo "Accès interdit";
        exit;
    }
}

/**
 * Vérifie une capacité (RBAC simple)
 */
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

    return isset($permissions[$role]) 
        && in_array($capability, $permissions[$role], true);
}

/**
 * Raccourci admin
 */
function requireAdmin(): void
{
    requireRole('admin');
}
