<?php
declare(strict_types=1);

session_start();

define('PROJECT_ROOT', dirname(__DIR__));
define('PUBLIC_ROOT', __DIR__);

require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/app/models/RecetteModel.php';
require_once PROJECT_ROOT . '/app/helpers/image_optimizer.php';
require_once PROJECT_ROOT . '/app/base_url.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Methode non autorisee';
    exit;
}

$recetteId = isset($_POST['recette_id']) ? (int) $_POST['recette_id'] : 0;
$source = (string) ($_POST['preview_source'] ?? '');
if ($recetteId <= 0 || !in_array($source, ['tmp', 'inline'], true)) {
    header('Location: ' . PUBLIC_URL . '/index.php');
    exit;
}

$model = new RecetteModel();
$recette = $model->getRecetteById($recetteId);
if (!$recette) {
    header('Location: ' . PUBLIC_URL . '/edit_recette.php?id=' . $recetteId . '&ai_apply=error&reason=recette_introuvable');
    exit;
}

try {
    $imageBinary = '';
    $tmpPreviewPath = null;

    if ($source === 'tmp') {
        $candidate = basename((string) ($_POST['ai_preview'] ?? ''));
        if (!preg_match('/^ai_preview_[a-zA-Z0-9.]+\.(png|webp|jpg|jpeg)$/', $candidate)) {
            throw new Exception('Apercu temporaire invalide');
        }

        $path = PUBLIC_ROOT . '/uploads/tmp/' . $candidate;
        if (!is_file($path)) {
            throw new Exception('Apercu temporaire introuvable');
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            throw new Exception('Impossible de lire l apercu temporaire');
        }
        $imageBinary = $raw;
        $tmpPreviewPath = $path;
    } else {
        $b64 = $_SESSION['ai_preview_inline'] ?? '';
        if (!is_string($b64) || $b64 === '') {
            throw new Exception('Apercu session introuvable');
        }
        $decoded = base64_decode($b64, true);
        if ($decoded === false || $decoded === '') {
            throw new Exception('Apercu session invalide');
        }
        $imageBinary = $decoded;
    }

    // Re-optimisation de securite avant sauvegarde finale
    $optimized = optimizeImageBinaryForWeb($imageBinary);
    $finalBinary = $optimized['binary'];
    $extension = $optimized['extension'];

    $uploadDir = PUBLIC_ROOT . '/uploads/recettes/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new Exception('Dossier uploads/recettes indisponible');
    }

    $filename = uniqid('recette_ai_', true) . '.' . $extension;
    $destination = $uploadDir . $filename;
    $saved = false;
    $writeErrors = [];

    // Methode 1: copy depuis tmp si source tmp
    if ($source === 'tmp' && is_string($tmpPreviewPath) && is_file($tmpPreviewPath)) {
        if (@copy($tmpPreviewPath, $destination)) {
            $saved = true;
        } else {
            $err = error_get_last();
            $writeErrors[] = 'copy(tmp->destination): ' . (($err['message'] ?? 'erreur inconnue'));
        }
    }

    // Methode 2: fopen/fwrite
    if (!$saved) {
        $fp = @fopen($destination, 'wb');
        if ($fp !== false) {
            $bytes = @fwrite($fp, $finalBinary);
            @fclose($fp);
            if ($bytes !== false && $bytes > 0) {
                $saved = true;
            } else {
                $err = error_get_last();
                $writeErrors[] = 'fwrite: ' . (($err['message'] ?? 'erreur inconnue'));
            }
        } else {
            $err = error_get_last();
            $writeErrors[] = 'fopen: ' . (($err['message'] ?? 'erreur inconnue'));
        }
    }

    // Methode 3: file_put_contents
    if (!$saved) {
        if (@file_put_contents($destination, $finalBinary) !== false) {
            $saved = true;
        } else {
            $err = error_get_last();
            $writeErrors[] = 'file_put_contents: ' . (($err['message'] ?? 'erreur inconnue'));
        }
    }

    if (!$saved) {
        $context = 'owner=' . (function_exists('fileowner') ? (string) @fileowner($uploadDir) : 'na')
            . ', perms=' . substr(sprintf('%o', @fileperms($uploadDir)), -4);
        error_log('[save_ai_preview_photo] Echec ecriture image finale. ' . $context . '. Details: ' . implode(' | ', $writeErrors));
        throw new Exception('Impossible de sauvegarder la photo finale');
    }
    @chmod($destination, 0644);

    $model->ajouterPhoto($recetteId, $filename);
    $photoId = null;
    foreach ($model->getPhotosByRecette($recetteId) as $photo) {
        if (($photo['fichier'] ?? '') === $filename) {
            $photoId = (int) ($photo['id'] ?? 0);
            break;
        }
    }
    if ($photoId !== null && $photoId > 0) {
        $model->definirPhotoPrincipale($photoId, $recetteId);
    }
    if ($source === 'tmp' && is_string($tmpPreviewPath) && is_file($tmpPreviewPath)) {
        @unlink($tmpPreviewPath);
    }
    if ($source === 'inline') {
        unset($_SESSION['ai_preview_inline'], $_SESSION['ai_preview_inline_ext']);
    }

    header('Location: ' . PUBLIC_URL . '/edit_recette.php?id=' . $recetteId . '&ai_apply=ok');
    exit;
} catch (Throwable $e) {
    $reason = rawurlencode(substr($e->getMessage(), 0, 120));
    header('Location: ' . PUBLIC_URL . '/edit_recette.php?id=' . $recetteId . '&ai_apply=error&reason=' . $reason);
    exit;
}
