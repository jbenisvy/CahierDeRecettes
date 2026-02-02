<?php
require_once __DIR__ . '/../../config/database.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password_confirm'] ?? '';

    if ($nom === '' || $email === '' || $password === '' || $password2 === '') {
        $error = "Merci de remplir tous les champs.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresse email invalide.";
    } elseif ($password !== $password2) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($password) < 8) {
        $error = "Le mot de passe doit contenir au moins 8 caractères.";
    } else {
        // Vérifier unicité email
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $error = "Un compte existe déjà avec cet email.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare('
                INSERT INTO users (nom, email, password_hash, role)
                VALUES (?, ?, ?, ?)
            ');
            $stmt->execute([
                $nom,
                $email,
                $hash,
                'lecteur'
            ]);

            $success = "Compte créé avec succès. Vous pouvez maintenant vous connecter.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Créer un compte</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="login-page">

  <div class="login-card">

    <div class="login-logo">
      <img src="/assets/img/logo-memoire-saveur-fond-sombre.png" alt="Mémoire de Saveurs">
    </div>

    <h1>Créer un compte</h1>

    <?php if ($error): ?>
      <p class="login-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($success): ?>
      <p style="color:#065f46; font-weight:600;">
        <?= htmlspecialchars($success) ?>
      </p>
      <p><a href="/auth/login.php">Aller à la connexion</a></p>
    <?php endif; ?>

    <?php if (!$success): ?>
      <form method="post" class="login-form">

        <label>Nom</label>
        <input type="text" name="nom" required>

        <label>Email</label>
        <input type="email" name="email" required>

        <label>Mot de passe</label>
        <input type="password" name="password" required>

        <label>Confirmer le mot de passe</label>
        <input type="password" name="password_confirm" required>

        <button type="submit" class="btn btn-primary">
          Créer mon compte
        </button>
      </form>
    <?php endif; ?>

    <div class="login-links">
      <a href="/auth/login.php">← Retour à la connexion</a>
    </div>

  </div>

</body>
</html>
