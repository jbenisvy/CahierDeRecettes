<?php
declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
define('PUBLIC_ROOT', __DIR__);
session_start();

require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/app/models/RecetteModel.php';
require_once PROJECT_ROOT . '/app/services/ChatGPTService.php';
require_once PROJECT_ROOT . '/app/helpers/image_optimizer.php';
require_once PROJECT_ROOT . '/public/auth/auth_functions.php';
require_once PROJECT_ROOT . '/app/base_url.php';

require_login();
require_capability('edit_recette');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Methode non autorisee';
    exit;
}

$recetteId = isset($_POST['recette_id']) ? (int) $_POST['recette_id'] : 0;
if ($recetteId <= 0) {
    header('Location: ' . PUBLIC_URL . '/index.php');
    exit;
}

$model = new RecetteModel();
$recette = $model->getRecetteComplete($recetteId);
if (!$recette || empty($recette['recette'])) {
    header('Location: ' . PUBLIC_URL . '/edit_recette.php?id=' . $recetteId . '&ai_image=error&reason=recette_introuvable');
    exit;
}
if (
    ($_SESSION['user']['role'] ?? '') !== 'admin'
    && ($_SESSION['user']['nom'] ?? '') !== ($recette['recette']['auteur'] ?? '')
) {
    http_response_code(403);
    echo 'Acces interdit';
    exit;
}

try {
    $chat = new ChatGPTService();

    $imageBinary = $chat->genererImageRecette([
        'titre' => $recette['recette']['titre'] ?? '',
        'categorie' => $recette['recette']['categorie'] ?? '',
        'type_recette' => $recette['recette']['type_recette'] ?? '',
        'type_cuisson' => $recette['recette']['type_cuisson'] ?? '',
        'nombre_personnes' => $recette['recette']['nombre_personnes'] ?? '',
        'ingredients' => $recette['ingredients'] ?? [],
        'etapes' => $recette['etapes'] ?? []
    ]);
    $optimized = optimizeImageBinaryForWeb($imageBinary);
    $imageBinary = $optimized['binary'];
    $extension = $optimized['extension'];

    $uploadDir = PUBLIC_ROOT . '/uploads/recettes/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new Exception('Impossible de creer le dossier uploads/recettes');
    }

    $filename = uniqid('recette_ai_', true) . '.' . $extension;
    $destination = $uploadDir . $filename;
    $saved = false;
    $writeErrors = [];

    // Ecriture de secours dans tmp pour permettre la previsualisation
    $tmpDir = PUBLIC_ROOT . '/uploads/tmp/';
    if (!is_dir($tmpDir)) {
        @mkdir($tmpDir, 0755, true);
    }
    $previewFilename = 'ai_preview_' . uniqid('', true) . '.' . $extension;
    $previewPath = $tmpDir . $previewFilename;
    $previewWritten = (@file_put_contents($previewPath, $imageBinary) !== false);

    // Methode 1: fichier temporaire puis copy() (proche du flux upload classique)
    if ($previewWritten) {
        if (@copy($previewPath, $destination)) {
            $saved = true;
        } else {
            $copyErr = error_get_last();
            $writeErrors[] = 'copy(preview->destination): ' . (($copyErr['message'] ?? 'erreur inconnue'));
        }
    } else {
        $tmpFile = @tempnam($tmpDir, 'ai_');
        if ($tmpFile !== false) {
            $tmpWrite = @file_put_contents($tmpFile, $imageBinary);
            if ($tmpWrite !== false) {
                if (@copy($tmpFile, $destination)) {
                    $saved = true;
                } else {
                    $copyErr = error_get_last();
                    $writeErrors[] = 'copy(tmp->destination): ' . (($copyErr['message'] ?? 'erreur inconnue'));
                }
            } else {
                $tmpErr = error_get_last();
                $writeErrors[] = 'ecriture temp: ' . (($tmpErr['message'] ?? 'erreur inconnue'));
            }
            @unlink($tmpFile);
        } else {
            $writeErrors[] = 'tempnam impossible';
        }
    }

    // Methode 2: fopen/fwrite direct
    if (!$saved) {
        $fp = @fopen($destination, 'wb');
        if ($fp !== false) {
            $bytes = @fwrite($fp, $imageBinary);
            @fclose($fp);
            if ($bytes !== false && $bytes > 0) {
                $saved = true;
            } else {
                $fwErr = error_get_last();
                $writeErrors[] = 'fwrite: ' . (($fwErr['message'] ?? 'erreur inconnue'));
            }
        } else {
            $foErr = error_get_last();
            $writeErrors[] = 'fopen: ' . (($foErr['message'] ?? 'erreur inconnue'));
        }
    }

    // Methode 3: file_put_contents direct
    if (!$saved) {
        $written = @file_put_contents($destination, $imageBinary);
        if ($written !== false) {
            $saved = true;
        } else {
            $fpErr = error_get_last();
            $writeErrors[] = 'file_put_contents: ' . (($fpErr['message'] ?? 'erreur inconnue'));
        }
    }

    if (!$saved) {
        $context = 'uid=' . (function_exists('getmyuid') ? (string) getmyuid() : 'na')
            . ', owner=' . (function_exists('fileowner') ? (string) @fileowner($uploadDir) : 'na')
            . ', perms=' . substr(sprintf('%o', @fileperms($uploadDir)), -4);
        error_log('[generate_photo_ai] Echec ecriture image. ' . $context . '. Details: ' . implode(' | ', $writeErrors));
        if ($previewWritten) {
            header('Location: ' . PUBLIC_URL . '/edit_recette.php?id=' . $recetteId . '&ai_image=preview&ai_preview=' . rawurlencode($previewFilename));
            exit;
        }
        // Dernier fallback: previsualisation en session sans ecriture disque.
        $_SESSION['ai_preview_inline'] = base64_encode($imageBinary);
        $_SESSION['ai_preview_inline_ext'] = $extension;
        header('Location: ' . PUBLIC_URL . '/edit_recette.php?id=' . $recetteId . '&ai_image=preview_inline');
        exit;
    }

    if ($previewWritten && is_file($previewPath)) {
        @unlink($previewPath);
    }
    @chmod($destination, 0644);

    $model->ajouterPhoto($recetteId, $filename);

    header('Location: ' . PUBLIC_URL . '/edit_recette.php?id=' . $recetteId . '&ai_image=ok');
    exit;
} catch (Throwable $e) {
    $reason = rawurlencode(substr($e->getMessage(), 0, 120));
    header('Location: ' . PUBLIC_URL . '/edit_recette.php?id=' . $recetteId . '&ai_image=error&reason=' . $reason);
    exit;
}
