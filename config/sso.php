<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

return [
    'enabled' => filter_var(getenv('SSO_ENABLED') ?: ($_ENV['SSO_ENABLED'] ?? false), FILTER_VALIDATE_BOOL),
    'secret_key' => trim((string) (getenv('SSO_SECRET_KEY') ?: ($_ENV['SSO_SECRET_KEY'] ?? ''))),
    'portal_url' => trim((string) (getenv('SSO_PORTAL_URL') ?: ($_ENV['SSO_PORTAL_URL'] ?? ''))),
    'token_ttl' => max(30, (int) (getenv('SSO_TOKEN_TTL') ?: ($_ENV['SSO_TOKEN_TTL'] ?? 120))),
    'allowed_app_id' => trim((string) (getenv('SSO_ALLOWED_APP_ID') ?: ($_ENV['SSO_ALLOWED_APP_ID'] ?? 'memoire-de-saveurs'))),
];
