<?php

declare(strict_types=1);

$env = (string) (getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'prod'));
$env = trim($env);

if ($env === '') {
    $env = 'prod';
}

return [
    'env' => $env,
];
