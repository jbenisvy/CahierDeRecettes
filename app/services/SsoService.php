<?php
declare(strict_types=1);

final class SsoService
{
    private PDO $pdo;
    private array $config;

    public function __construct(PDO $pdo, array $config)
    {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    public function isEnabled(): bool
    {
        return !empty($this->config['enabled']) && $this->getSecretKey() !== '';
    }

    public function consumeToken(string $token): array
    {
        if (!$this->isEnabled()) {
            throw new RuntimeException('SSO disabled');
        }

        $parts = explode('.', trim($token));
        if (count($parts) !== 3) {
            throw new RuntimeException('Malformed token');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = $this->decodeJsonSegment($encodedHeader);
        $payload = $this->decodeJsonSegment($encodedPayload);

        if (($header['alg'] ?? '') !== 'HS256') {
            throw new RuntimeException('Unsupported SSO algorithm');
        }

        $expectedSignature = $this->base64UrlEncode(hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $this->getSecretKey(), true));
        if (!hash_equals($expectedSignature, $encodedSignature)) {
            throw new RuntimeException('Invalid SSO signature');
        }

        $this->validatePayload($payload);
        $this->assertNotReplayed($token, (int) $payload['exp']);

        $user = $this->findUser($payload);
        if ($user === null) {
            throw new RuntimeException('Unknown SSO user');
        }

        $this->markTokenAsUsed($token, (int) $payload['exp']);

        return $user;
    }

    private function getSecretKey(): string
    {
        return (string) ($this->config['secret_key'] ?? '');
    }

    private function decodeJsonSegment(string $segment): array
    {
        $decoded = base64_decode(strtr($segment, '-_', '+/') . str_repeat('=', (4 - strlen($segment) % 4) % 4), true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid SSO encoding');
        }

        $data = json_decode($decoded, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid SSO payload');
        }

        return $data;
    }

    private function validatePayload(array $payload): void
    {
        $now = time();
        $appId = trim((string) ($payload['app'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $exp = (int) ($payload['exp'] ?? 0);
        $iat = (int) ($payload['iat'] ?? 0);
        $ttl = (int) ($this->config['token_ttl'] ?? 120);

        if ($appId === '' || $appId !== (string) ($this->config['allowed_app_id'] ?? '')) {
            throw new RuntimeException('Unexpected SSO application');
        }
        if ($email === '') {
            throw new RuntimeException('Missing SSO email');
        }
        if ($iat <= 0 || $exp <= 0 || $exp <= $now) {
            throw new RuntimeException('Expired SSO token');
        }
        if ($iat > $now + 60) {
            throw new RuntimeException('Future-dated SSO token');
        }
        if (($exp - $iat) > $ttl + 60) {
            throw new RuntimeException('SSO token lifetime too long');
        }
    }

    private function findUser(array $payload): ?array
    {
        $email = trim((string) ($payload['email'] ?? ''));
        $userId = trim((string) ($payload['user_id'] ?? ''));

        if ($userId !== '' && ctype_digit($userId)) {
            $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([(int) $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user !== false) {
                if ($email !== '' && mb_strtolower((string) ($user['email'] ?? '')) !== mb_strtolower($email)) {
                    throw new RuntimeException('SSO user mismatch');
                }

                return $user;
            }
        }

        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user !== false ? $user : null;
    }

    private function replayDirectory(): string
    {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cahier_recettes_sso';
    }

    private function assertNotReplayed(string $token, int $expiresAt): void
    {
        $path = $this->replayDirectory() . DIRECTORY_SEPARATOR . hash('sha256', $token) . '.lock';
        if (is_file($path)) {
            $stored = (int) trim((string) @file_get_contents($path));
            if ($stored >= time()) {
                throw new RuntimeException('SSO token already used');
            }
            @unlink($path);
        }

        $this->cleanupReplayDirectory();
    }

    private function markTokenAsUsed(string $token, int $expiresAt): void
    {
        $dir = $this->replayDirectory();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . hash('sha256', $token) . '.lock';
        @file_put_contents($path, (string) $expiresAt, LOCK_EX);
    }

    private function cleanupReplayDirectory(): void
    {
        $dir = $this->replayDirectory();
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.lock');
        if ($files === false) {
            return;
        }

        $now = time();
        foreach ($files as $file) {
            $stored = (int) trim((string) @file_get_contents($file));
            if ($stored > 0 && $stored < $now) {
                @unlink($file);
            }
        }
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
