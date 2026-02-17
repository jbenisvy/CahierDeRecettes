<?php
declare(strict_types=1);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$_GET['path'] = 'recipes/' . $id;
require dirname(__DIR__) . '/api.php';
