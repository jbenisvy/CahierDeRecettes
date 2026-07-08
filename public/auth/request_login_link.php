<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/base_url.php';
require_once __DIR__ . '/../../app/services/MailService.php';
require_once __DIR__ . '/../../app/services/LoginLinkService.php';

$pdo = getPDO();

$message = null;
$mailDebugError = null;
$debugLoginLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $message = "Si un compte existe avec cet email, un lien de connexion a été envoyé.";

    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare('SELECT id, nom, email, role FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $token = LoginLinkService::issue($pdo, (int) $user['id']);
            $loginLink = app_absolute_url('?action=login_link&token=' . urlencode($token));
            $debugLoginLink = $loginLink;

            $nom = htmlspecialchars((string) ($user['nom'] ?? ''), ENT_QUOTES, 'UTF-8');
            $subject = 'Votre lien de connexion';
            $html = "
                <p>Bonjour {$nom},</p>
                <p>Cliquez sur le lien ci-dessous pour vous connecter à Mémoire de Saveurs :</p>
                <p><a href=\"{$loginLink}\">Se connecter</a></p>
                <p>Ce lien est personnel, utilisable une seule fois et expire dans 20 minutes.</p>
            ";

            $sent = MailService::send((string) $user['email'], $subject, $html);
            if (!$sent) {
                error_log('Mail lien de connexion non envoyé pour ' . $user['email']);
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
    <title>Lien de connexion</title>
    <?php require __DIR__ . '/../ui/pwa_head.php'; ?>
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>/assets/css/style.css">
</head>
<body class="login-page">
  <div class="login-card">
    <h1>Connexion par lien sécurisé</h1>
    <p class="login-intro">Saisis ton email. Si un compte existe, nous t’enverrons un lien de connexion à usage unique.</p>

    <?php if ($message): ?>
      <p class="login-success"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if ($debugLoginLink && getenv('MAIL_DEBUG') === '1'): ?>
      <p><strong>Lien de connexion (DEV) :</strong><br>
        <a href="<?= htmlspecialchars($debugLoginLink) ?>"><?= htmlspecialchars($debugLoginLink) ?></a>
      </p>
    <?php endif; ?>

    <?php if ($mailDebugError && getenv('MAIL_DEBUG') === '1'): ?>
      <p class="login-error">Erreur d'envoi mail (DEBUG): <?= htmlspecialchars($mailDebugError) ?></p>
    <?php endif; ?>

    <form method="post" class="login-form">
      <label>Email</label>
      <input type="email" name="email" required>

      <button type="submit" class="btn btn-primary">M'envoyer un lien</button>
    </form>

    <div class="login-links">
      <a href="<?= BASE_URL ?>/?action=login">← Retour à la connexion</a>
    </div>
  </div>
  <?php require __DIR__ . '/../ui/brand_signature.php'; ?>
</body>
</html>
