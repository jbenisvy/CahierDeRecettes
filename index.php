<?php
// =======================================
// Front-controller – CahierDeRecettes
// =======================================

// Sessions
session_start();

// Affichage des erreurs (à désactiver en prod si tu veux)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ---------------------------------------
// Base URL (OBLIGATOIRE AVANT TOUT)
// ---------------------------------------
require_once __DIR__ . '/app/base_url.php';

// ---------------------------------------
// Fonctions communes
// ---------------------------------------
require_once __DIR__ . '/public/auth/auth_functions.php';

// ---------------------------------------
// Action demandée
// ---------------------------------------
$action = $_GET['action'] ?? 'home';

// ---------------------------------------
// Routage principal
// ---------------------------------------
switch ($action) {

    // ===== PAGE D’ACCUEIL (publique) =====
    case 'home':
        require_once __DIR__ . '/app/controllers/HomeController.php';
        break;

    // ===== AUTHENTIFICATION =====
    case 'login':
        require_once __DIR__ . '/public/auth/login.php';
        break;

    case 'logout':
        require_once __DIR__ . '/public/auth/logout.php';
        break;

    case 'register':
        require_once __DIR__ . '/public/auth/register.php';
        break;

    // ===== EXEMPLE DE PAGE PROTÉGÉE =====
    case 'dashboard':
        require_login(); // 🔐 protection ICI, pas avant
        require_once __DIR__ . '/app/controllers/DashboardController.php';
        break;

    // ===== FALLBACK =====
    default:
        // Action inconnue → home
        require_once __DIR__ . '/app/controllers/HomeController.php';
        break;
}
