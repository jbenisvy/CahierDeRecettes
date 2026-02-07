<?php
require_once __DIR__ . '/../../config/database.php';
// Définir BASE_URL et PUBLIC_URL pour gérer les redirections
require_once __DIR__ . '/../../app/base_url.php';

session_destroy();

header('Location: ' . BASE_URL . '/?action=login');
exit;
