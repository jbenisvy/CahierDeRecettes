<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/base_url.php';
require_once __DIR__ . '/../../app/services/MailService.php';

$pdo = getPDO();

$error = null;
$message = null;
$mailDebugError = null;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    if ($nom === '' || $email === '') {
        $error = "Merci de remplir tous les champs.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresse email invalide.";
    } else {
        // Vérifier unicité email
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $error = "Un compte existe déjà avec cet email.";
        } else {
            $hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);

            $stmt = $pdo->prepare('
                INSERT INTO users (nom, email, password_hash, role)
                VALUES (?, ?, ?, ?)
            ');
            $stmt->execute([$nom, $email, $hash, 'lecteur']);

            $userId = (int) $pdo->lastInsertId();

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS password_resets (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    token CHAR(64) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            ");

            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+2 hours'));

            $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$userId]);
            $stmt = $pdo->prepare('
                INSERT INTO password_resets (user_id, token, expires_at)
                VALUES (?, ?, ?)
            ');
            $stmt->execute([$userId, $tokenHash, $expiresAt]);

            $setLink = app_absolute_url('?action=reset_password&token=' . urlencode($token));
            $subject = "Activez votre compte";
            $html = "
                <p>Bonjour {$nom},</p>
                <p>Bienvenue sur Mémoire de Saveurs. Cliquez ci-dessous pour choisir votre mot de passe :</p>
                <p><a href=\"{$setLink}\">Définir mon mot de passe</a></p>
                <p>Ce lien expire dans 2 heures.</p>
            ";

            $sent = MailService::send($email, $subject, $html);
            if (!$sent) {
                error_log('Mail activation non envoyé pour ' . $email);
                if ((getenv('MAIL_DEBUG') ?: '0') === '1') {
                    $mailDebugError = MailService::getLastError() ?: 'Erreur inconnue SMTP';
                }
            }

            $message = "Un email vient d'être envoyé pour définir votre mot de passe.";


        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Créer un compte</title>
  <link rel="stylesheet" href="<?= PUBLIC_URL ?>/assets/css/style.css">
</head>
<body class="login-page">

  <div class="login-card">

    <div class="login-logo">
      <img src="<?= PUBLIC_URL ?>/assets/img/logo-memoire-saveur-fond-sombre.png" alt="Mémoire de Saveurs">
    </div>

    <h1>Créer un compte</h1>

   <?php if ($error): ?>
  <p class="login-error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<?php if ($message): ?>
  <p class="login-success"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<?php if ($mailDebugError && (getenv('MAIL_DEBUG') ?: '0') === '1'): ?>
  <p class="login-error">Erreur d'envoi mail (DEBUG): <?= htmlspecialchars($mailDebugError) ?></p>
<?php endif; ?>

<form method="post" class="login-form">

  <label>Nom</label>
  <input type="text" name="nom" required>

  <label>Email</label>
  <input type="email" name="email" required>

  <button type="submit" class="btn btn-primary">
    Créer mon compte
  </button>

</form>


    <div class="login-links">
    <a href="<?= BASE_URL ?>/?action=login">← Retour à la connexion</a>

    </div>

  </div>

</body>
</html>
