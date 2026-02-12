<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/base_url.php';
require_once __DIR__ . '/../auth/auth_functions.php';

require_admin();

header('Location: ' . PUBLIC_URL . '/admin/settings.php');
exit;
