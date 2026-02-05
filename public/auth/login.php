<?php
session_start();
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/database.php';
// Charge la définition des constantes BASE_URL et PUBLIC_URL pour les liens dynamiques
require_once __DIR__ . '/../../app/base_url.php';
$pdo = getPDO(); // ✅ OBLIGATOIRE
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // 🔍 DEBUG TEMPORAIRE (à enlever après test)
    // var_dump($email);
    // var_dump($password);

    if ($email && $password) {

        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // 🔍 DEBUG TEMPORAIRE
        // var_dump($user);
        // var_dump(password_verify($password, $user['password_hash'] ?? ''));
        // exit;

        if ($user && password_verify($password, $user['password_hash'])) {

       $_SESSION['user'] = [
  'id'   => (int)$user['id'],   // ← ⭐⭐ ESSENTIEL ⭐⭐
  'nom'  => $user['nom'],
  'role' => $user['role']
];



            header('Location: ' . BASE_URL . '/');
			exit;


        } else {
            $error = "Email ou mot de passe incorrect";
        }

    } else {
        $error = "Merci de remplir tous les champs";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion</title>
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>/assets/css/style.css">
</head>
<body class="login-page">

  <div class="login-card">

    <!-- LOGO -->
    <div class="login-logo">
      <img src="<?= PUBLIC_URL ?>/assets/img/logo-memoire-saveurs.png"
           alt="Mémoire de Saveurs">
    </div>

    <!-- TITRE + MESSAGE -->
    <h1>Bienvenue</h1>
    <p class="login-intro">
      Connectez-vous pour retrouver vos recettes, vos favoris et vos notes personnelles.
    </p>

    <!-- ERREUR -->
    <?php if ($error): ?>
      <p class="login-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <!-- FORMULAIRE -->
    <form method="post" class="login-form">

      <label>Email</label>
      <input type="email" name="email" required>

      <label>Mot de passe</label>
      <input type="password" name="password" required>

      <button type="submit" class="btn btn-primary">
        Se connecter
      </button>

    </form>

    <!-- MOT DE PASSE OUBLIÉ -->
    <div class="login-links">
 
<a href="<?= BASE_URL ?>/?action=register"><strong>Créer un compte</strong></a>

</div>

    <div class="login-links">
      <a href="<?= BASE_URL ?>/?action=forgot_password">Mot de passe oublié ?</a>

    </div>

  </div>

</body>

</html>
