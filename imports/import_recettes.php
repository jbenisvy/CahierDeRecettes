<?php
// Sécurité : script lancé uniquement en CLI
if (php_sapi_name() !== 'cli') {
    die("Accès interdit\n");
}

require_once __DIR__ . '/../config/database.php';
$pdo = getPDO();


