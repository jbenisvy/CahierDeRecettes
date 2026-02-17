<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/base_url.php';
require_once __DIR__ . '/../../app/services/MailService.php';

$pdo = getPDO();

$message = null;
$resetLink = null;
$mailDebugError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    // Message neutre, qu'on affichera quoi qu'il arrive (anti-enumération)
    $message = "Si un compte existe avec cet email, un lien de réinitialisation a été généré.";

    if ($email !== '') {
        // 1) Chercher l'utilisateur
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS password_resets (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    token CHAR(64) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // 2) Générer un token (celui-ci sera dans l'URL)
            $token = bin2hex(random_bytes(32)); // 64 caractères hex

            // 3) Par sécurité, on stocke en base le HASH du token
            $tokenHash = hash('sha256', $token);

            // 4) Expiration
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // 5) Optionnel mais propre : supprimer les anciens tokens de cet utilisateur
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$user['id']]);

            // 6) Insérer en base
            $stmt = $pdo->prepare('
                INSERT INTO password_resets (user_id, token, expires_at)
                VALUES (?, ?, ?)
            ');
            $stmt->execute([$user['id'], $tokenHash, $expiresAt]);

            // 7) Lien de reset
            $resetLink = app_absolute_url('?action=reset_password&token=' . urlencode($token));

            $subject = "Réinitialisation de votre mot de passe";
            $html = "
                <p>Bonjour,</p>
                <p>Pour définir un nouveau mot de passe, cliquez sur ce lien :</p>
                <p><a href=\"{$resetLink}\">Réinitialiser mon mot de passe</a></p>
                <p>Ce lien expire dans 1 heure.</p>
            ";

            $sent = MailService::send($email, $subject, $html);
            if (!$sent) {
                error_log('Mail reset non envoyé pour ' . $email);
                if ((getenv('MAIL_DEBUG') ?: '0') === '1') {
                    $mailDebugError = MailService::getLastError() ?: 'Erreur inconnue SMTP';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mot de passe oublié</title>
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>/assets/css/style.css">
</head>
<body class="login-page">
  <div class="login-card">
    <h1>Mot de passe oublié</h1>
    <p class="login-intro">Saisis ton email. Si un compte existe, tu pourras réinitialiser ton mot de passe.</p>

    <?php if ($message): ?>
      <p><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if ($resetLink && getenv('MAIL_DEBUG') === '1'): ?>
      <p><strong>Lien de réinitialisation (DEV) :</strong><br>
        <a href="<?= htmlspecialchars($resetLink) ?>"><?= htmlspecialchars($resetLink) ?></a>
      </p>
    <?php endif; ?>

    <?php if ($mailDebugError && getenv('MAIL_DEBUG') === '1'): ?>
      <p class="login-error">
        Erreur d'envoi mail (DEBUG): <?= htmlspecialchars($mailDebugError) ?>
      </p>
    <?php endif; ?>

    <form method="post">
      <label>Email</label><br>
      <input type="email" name="email" required><br><br>
      <button class="btn btn-primary" type="submit">Générer le lien</button>
    </form>

    <p style="margin-top:14px;">
      <a href="<?= BASE_URL ?>/?action=login" class="btn btn-ghost btn-small">← Retour connexion</a>
    </p>
  </div>
</body>
</html>
