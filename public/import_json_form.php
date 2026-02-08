<?php
// Session + sécurité
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die('Accès refusé');
}

// Layout
$page = 'import';
$bodyClass = 'page-import';
$useBootstrap = true;

require __DIR__ . '/ui/layout_start.php';
?>

<div class="container my-5 import-shell">

  <div class="row justify-content-center">
    <div class="col-12 col-lg-8">

      <div class="card shadow-sm border-0">
        <div class="card-body p-4 p-md-5">

          <h1 class="mb-2">Importer une recette</h1>
          <p class="text-muted mb-4">
            Choisissez le mode d’importation le plus adapté.
          </p>

          <!-- 🔹 Onglets -->
          <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item">
              <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#import-json">
                📄 JSON
              </button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#import-image">
                📷 Image
              </button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#import-url">
                🌐 URL
              </button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#import-text">
                ✍️ Texte
              </button>
            </li>
          </ul>

          <div class="tab-content">

            <!-- 🟦 IMPORT JSON -->
            <div class="tab-pane fade show active" id="import-json">
              <form action="<?= PUBLIC_URL ?>/import_json.php" method="post" enctype="multipart/form-data">
                <div class="mb-3">
                  <label class="form-label">Fichier JSON</label>
                  <input type="file" name="jsonfile" class="form-control" accept=".json" required>
                </div>
                <button class="btn btn-primary">
                  ➕ Importer le fichier JSON
                </button>
              </form>
            </div>

            <!-- 🟩 IMPORT IMAGE -->
            <div class="tab-pane fade" id="import-image">
              <form action="<?= PUBLIC_URL ?>/import_recette_image.php" method="post" enctype="multipart/form-data">
                <div class="mb-3">
                  <label class="form-label">Image de la recette</label>
                  <input type="file"
                         name="image"
                         class="form-control"
                         accept="image/*"
                         capture="environment"
                         required>
                  <div class="form-text">
                    Sur mobile : caméra ou galerie • Sur PC : fichier image
                  </div>
                </div>
                <button class="btn btn-success">
                  🤖 Analyser l’image
                </button>
              </form>
            </div>

            <!-- 🟨 IMPORT URL -->
            <div class="tab-pane fade" id="import-url">
              <form action="<?= PUBLIC_URL ?>/import_recette_url.php" method="post">
                <div class="mb-3">
                  <label class="form-label">URL de la recette</label>
                  <input type="url"
                         name="url"
                         class="form-control"
                         placeholder="https://exemple.com/recette"
                         required>
                </div>
                <button class="btn btn-info">
                  🌐 Analyser la page
                </button>
              </form>
            </div>

            <!-- 🟧 IMPORT TEXTE -->
            <div class="tab-pane fade" id="import-text">
              <form action="<?= PUBLIC_URL ?>/import_recette_texte.php" method="post">
                <div class="mb-3">
                  <label class="form-label">Texte de la recette</label>
                  <textarea name="texte"
                            class="form-control"
                            rows="6"
                            placeholder="Collez ici le texte brut de la recette…"
                            required></textarea>
                </div>
                <button class="btn btn-warning">
                  ✍️ Analyser le texte
                </button>
              </form>
            </div>

          </div>

          <hr class="my-4">

          <a href="<?= PUBLIC_URL ?>/index.php" class="btn btn-outline-secondary">
            ← Retour aux recettes
          </a>

        </div>
      </div>

    </div>
  </div>

</div>

<?php
require __DIR__ . '/ui/footer.php';
require __DIR__ . '/ui/layout_end.php';
