<?php
// Ce fichier détermine dynamiquement l'URL de base de l'application.
//
// L'objectif est de pouvoir déployer l'application dans un sous-dossier (ex : /CahierDeRecettes)
// sans avoir à modifier manuellement tous les chemins des liens, redirections ou ressources.
//
// On se base sur la variable SERVER SCRIPT_NAME : elle contient le chemin de la page courante.
// On extrait tout ce qui précède le dossier /public pour obtenir le sous-répertoire racine.
//
// Exemple :
//  - SCRIPT_NAME : /CahierDeRecettes/public/index.php
//  - $baseUrl    : /CahierDeRecettes
// Ainsi, PUBLIC_URL deviendra /CahierDeRecettes/public
//
// Si l'application est installée à la racine du domaine, $baseUrl sera une chaîne vide.

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
// Cherche la présence d'un sous-dossier `/public/` dans le chemin du script.
// Si présent, cela signifie que l'application est installée dans un répertoire parent
// (ex : /CahierDeRecettes/public/index.php). Dans ce cas, la partie avant `/public/`
// constitue BASE_URL et `/public` est le dossier public.
$publicPos = strpos($scriptName, '/public/');

if ($publicPos !== false) {
    // Exemple : /CahierDeRecettes/public/index.php -> BASE_URL = /CahierDeRecettes
    $baseUrl = substr($scriptName, 0, $publicPos);
    $publicUrl = $baseUrl . '/public';
} else {
    // Aucun dossier `/public/` dans le chemin. Cela signifie généralement que
    // le document root du serveur est déjà le dossier `public` de l'application.
    // Dans ce cas, il ne faut pas préfixer les liens par `/public` car cela
    // provoquerait des redirections infinies. On utilise la racine (`''`).
    $baseUrl = '';
    $publicUrl = $baseUrl;
}

// Définition des constantes uniquement si elles n'existent pas déjà.
if (!defined('BASE_URL')) {
    define('BASE_URL', rtrim($baseUrl, '/'));
}
if (!defined('PUBLIC_URL')) {
    // PUBLIC_URL se termine sans slash final pour permettre l'ajout de chemins relatifs
    define('PUBLIC_URL', rtrim($publicUrl, '/'));
}

// Fin du fichier
