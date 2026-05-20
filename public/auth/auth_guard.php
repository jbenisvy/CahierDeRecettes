<?php

require_once __DIR__ . '/auth_functions.php';

$publicActions = ['login', 'register', 'forgot_password', 'reset_password', 'request_login_link', 'login_link'];
$action = $_GET['action'] ?? null;

if (in_array($action, $publicActions, true)) {
    return;
}

require_login();
