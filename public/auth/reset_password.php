<?php
require_once __DIR__ . '/../../config/database.php';

$token = $_GET['token'] ?? '';
$token = trim($token);

$error = null;
$success = null;
$valid = false;
$resetRow = null;

if ($token !== '') {
    // On compare avec le HASH stocké
    $tokenHash = hash('sha256', $token);

    $stmt = $pdo->prepare('
        SELECT pr.id, pr.user_id, pr.expires_at
        FROM password_resets pr
        WHERE pr.token = ?
        LIMIT 1
    ');
    $stmt->execute([$tokenHash]);
    $resetRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resetRow) {
        // Vérifier expiration
        if (strtotime($resetRow['expires_at']) >= time()) {
            $valid = true;
        } else {
            $error = "Lien expiré. Merci de refaire une demande.";
        }
    } else {
        $error = "Lien invalide. Merci de refaire une demande.";
    }
} else {
    $error = "Token manquant.";
}

// Traitement du formulaire si token valide
if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass1 = $_POST['password'] ?? '';
    $pass2 = $_POST['password_confirm'] ?? '';

    if ($pass1 === '' || $pass2 === '') {
        $error = "Merci de remplir les deux champs.";
    } elseif ($pass1 !== $pass2) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($pass1) < 8) {
        $error = "Le mot de passe doit faire au moins 8 caractères.";
    } else {
        $newHash = password_hash($pass1, PASSWORD_DEFAULT);

        // 1) Mettre à jour l'utilisateur
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$newHash, $resetRow['user_id']]);

        // 2) Invalider le token (usage unique)
        $stmt = $pdo->prepare('DELETE FROM password_resets WHERE id = ?');
        $stmt->execute([$resetRow['id']]);

        // 3) Option: déconnecter toute session existante (facultatif)
        // session_destroy();

        $success = "Mot de passe mis à jour. Tu peux te connecter.";
        $valid = false; // on n'affiche plus le form
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Réinitialiser le mot de passe</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="login-page">
  <div class="login-card">
    <h1>Réinitialiser le mot de passe</h1>

    <?php if ($error): ?>
      <p style="color:#b91c1c;"><strong><?= htmlspecialchars($error) ?></strong></p>
      <p><a href="/auth/forgot_password.php">Recommencer</a></p>
    <?php endif; ?>

    <?php if ($success): ?>
      <p style="color:#065f46;"><strong><?= htmlspecialchars($success) ?></strong></p>
      <p><a href="/auth/login.php">Aller à la connexion</a></p>
    <?php endif; ?>

    <?php if ($valid): ?>
      <form method="post">
        <label>Nouveau mot de passe</label><br>
        <input type="password" name="password" required><br><br>

        <label>Confirmer le mot de passe</label><br>
        <input type="password" name="password_confirm" required><br><br>

        <button class="btn btn-primary" type="submit">Valider</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
