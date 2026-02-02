<?php

function nettoyerDossierTmp(string $dir, int $maxAgeSeconds = 7200): void
{
    if (!is_dir($dir)) {
        return;
    }

    $now = time();

    foreach (glob($dir . '/*') as $file) {
        if (!is_file($file)) {
            continue;
        }

        if ($now - filemtime($file) > $maxAgeSeconds) {
            @unlink($file);
        }
    }
}
