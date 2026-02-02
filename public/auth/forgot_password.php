<?php
require_once __DIR__ . '/../../config/database.php';

$message = null;
$resetLink = null;

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

            // 7) En DEV (localhost) : on affiche le lien
            // En prod : on l'enverra par email
            $resetLink = '/auth/reset_password.php?token=' . urlencode($token);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mot de passe oublié</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="login-page">
  <div class="login-card">
    <h1>Mot de passe oublié</h1>
    <p class="login-intro">Saisis ton email. Si un compte existe, tu pourras réinitialiser ton mot de passe.</p>

    <?php if ($message): ?>
      <p><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if ($resetLink): ?>
      <p><strong>Lien de réinitialisation (DEV) :</strong><br>
        <a href="<?= htmlspecialchars($resetLink) ?>"><?= htmlspecialchars($resetLink) ?></a>
      </p>
    <?php endif; ?>

    <form method="post">
      <label>Email</label><br>
      <input type="email" name="email" required><br><br>
      <button class="btn btn-primary" type="submit">Générer le lien</button>
    </form>

    <p style="margin-top:14px;">
      <a href="/auth/login.php" class="btn btn-ghost btn-small">← Retour connexion</a>
    </p>
  </div>
</body>
</html>
