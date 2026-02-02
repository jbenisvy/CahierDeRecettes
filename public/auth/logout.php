<?php
require_once __DIR__ . '/../../config/database.php';

session_destroy();

header('Location: /auth/login.php');
exit;
