<?php
session_start();

require_once __DIR__ . '/../../app/base_url.php';

require_once __DIR__ . '/../auth/auth_functions.php';

require_admin();

require_once __DIR__ . '../../../config/database.php';


// 🔑 Récupération explicite du PDO
$pdo = getPDO();



// Traitement du changement de rôle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $role   = $_POST['role'] ?? '';

    if ($userId && in_array($role, ['lecteur', 'contributeur', 'admin'], true)) {
        $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
        $stmt->execute([$role, $userId]);
    }

    header('Location: users.php');
    exit;
}

// Récupération des utilisateurs
$stmt = $pdo->query('SELECT id, nom, email, role FROM users ORDER BY nom');
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);


$page = 'admin-users';
require_once __DIR__ . '/../ui/header.php';
?>

<style>
/* Masquer le logo uniquement sur la page admin utilisateurs */
.app-topbar .app-logo-link {
  display: none;
}
.app-topbar {
  background: #111;
  color: #fff;
  padding: 10px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.app-topbar a {
  color: #fff;
  text-decoration: none;
}

.app-topbar .btn {
  color: #fff;
}
</style>






<div class="page page-admin">

 <div style="margin-bottom:20px;">
  <h1 style="margin-bottom:6px;">Gestion des utilisateurs</h1>
  <div style="color:#666; font-size:0.95em;">
    Administration des comptes et des rôles
  </div>
</div>


<div style="background:#fff; border:1px solid #ddd; border-radius:6px; padding:8px;">
  <table class="recettes-table">

    <thead>
      <tr>
        <th>Nom</th>
        <th>Email</th>
        <th>Rôle</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= htmlspecialchars($u['nom']) ?></td>
          <td><?= htmlspecialchars($u['email']) ?></td>
        <td>
  <span style="
    padding:2px 6px;
    border-radius:4px;
    font-size:0.85em;
    background:
      <?= $u['role'] === 'admin' ? '#fdecea' :
          ($u['role'] === 'contributeur' ? '#eef6ff' : '#f4f4f4') ?>;
    color:
      <?= $u['role'] === 'admin' ? '#b71c1c' :
          ($u['role'] === 'contributeur' ? '#0d47a1' : '#444') ?>;
  ">
    <?= htmlspecialchars($u['role']) ?>
  </span>
</td>

          <td>
            <?php if ($u['id'] !== $_SESSION['user']['id']): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <select name="role">
                  <option value="lecteur" <?= $u['role'] === 'lecteur' ? 'selected' : '' ?>>Lecteur</option>
                  <option value="contributeur" <?= $u['role'] === 'contributeur' ? 'selected' : '' ?>>Contributeur</option>
                  <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
                <button class="btn btn-small btn-primary" type="submit">
                  Mettre à jour
                </button>
              </form>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>

</div>
<?php
require_once __DIR__ . '/../ui/footer.php';
