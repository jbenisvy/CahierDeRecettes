<?php

class LoginLinkService
{
    public static function ensureTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS login_links (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                token CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_login_links_token (token),
                KEY idx_login_links_user_id (user_id),
                KEY idx_login_links_expires_at (expires_at)
            )
        ");
    }

    public static function issue(PDO $pdo, int $userId, int $ttlMinutes = 20): string
    {
        self::ensureTable($pdo);

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $ttlMinutes . ' minutes'));

        $pdo->prepare('DELETE FROM login_links WHERE user_id = ?')->execute([$userId]);

        $stmt = $pdo->prepare('
            INSERT INTO login_links (user_id, token, expires_at)
            VALUES (?, ?, ?)
        ');
        $stmt->execute([$userId, $tokenHash, $expiresAt]);

        return $token;
    }

    public static function consume(PDO $pdo, string $token): ?array
    {
        self::ensureTable($pdo);

        $tokenHash = hash('sha256', $token);

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('
                SELECT ll.id, ll.user_id, u.nom, u.email, u.role
                FROM login_links ll
                INNER JOIN users u ON u.id = ll.user_id
                WHERE ll.token = ?
                  AND ll.used_at IS NULL
                  AND ll.expires_at >= NOW()
                LIMIT 1
                FOR UPDATE
            ');
            $stmt->execute([$tokenHash]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $pdo->commit();
                return null;
            }

            $update = $pdo->prepare('UPDATE login_links SET used_at = NOW() WHERE id = ?');
            $update->execute([(int) $row['id']]);

            $pdo->commit();

            return [
                'id' => (int) $row['user_id'],
                'nom' => (string) $row['nom'],
                'email' => (string) $row['email'],
                'role' => (string) $row['role'],
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}
