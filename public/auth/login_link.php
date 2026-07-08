<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/base_url.php';
require_once __DIR__ . '/../../app/services/LoginLinkService.php';
require_once __DIR__ . '/auth_functions.php';

$pdo = getPDO();
$error = null;

$token = trim((string) ($_GET['token'] ?? ''));

if ($token === '') {
    $error = 'Lien de connexion invalide.';
} else {
    try {
        $user = LoginLinkService::consume($pdo, $token);

        if ($user === null) {
            $error = 'Ce lien de connexion est invalide, expiré ou a déjà été utilisé.';
        } else {
            login_user($user, $pdo);
            header('Location: ' . BASE_URL . '/');
            exit;
        }
    } catch (Throwable $e) {
        $error = 'Une erreur est survenue lors de la connexion sécurisée.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion par lien</title>
    <?php require __DIR__ . '/../ui/pwa_head.php'; ?>
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>/assets/css/style.css">
</head>
<body class="login-page">
  <div class="login-card">
    <h1>Connexion impossible</h1>
    <p class="login-error"><?= htmlspecialchars($error ?? 'Lien invalide.') ?></p>
    <div class="login-links">
      <a href="<?= BASE_URL ?>/?action=request_login_link">Demander un nouveau lien</a>
    </div>
    <div class="login-links">
      <a href="<?= BASE_URL ?>/?action=login">← Retour à la connexion</a>
    </div>
  </div>
  <?php require __DIR__ . '/../ui/brand_signature.php'; ?>
</body>
</html>
