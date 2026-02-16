<?php
declare(strict_types=1);

function optimizeImageBinaryForWeb(string $imageBinary): array
{
    if (!function_exists('imagecreatefromstring')) {
        return [
            'binary' => $imageBinary,
            'extension' => 'png',
            'mime' => 'image/png',
            'optimized' => false
        ];
    }

    $maxDim = (int) (getenv('OPENAI_IMAGE_MAX_DIM') ?: ($_ENV['OPENAI_IMAGE_MAX_DIM'] ?? 512));
    if ($maxDim < 256) {
        $maxDim = 256;
    }
    if ($maxDim > 1600) {
        $maxDim = 1600;
    }

    $src = @imagecreatefromstring($imageBinary);
    if ($src === false) {
        return [
            'binary' => $imageBinary,
            'extension' => 'png',
            'mime' => 'image/png',
            'optimized' => false
        ];
    }

    $srcW = imagesx($src);
    $srcH = imagesy($src);
    if ($srcW <= 0 || $srcH <= 0) {
        imagedestroy($src);
        return [
            'binary' => $imageBinary,
            'extension' => 'png',
            'mime' => 'image/png',
            'optimized' => false
        ];
    }

    $scale = min(1.0, $maxDim / max($srcW, $srcH));
    $dstW = max(1, (int) floor($srcW * $scale));
    $dstH = max(1, (int) floor($srcH * $scale));

    $dst = imagecreatetruecolor($dstW, $dstH);
    if ($dst === false) {
        imagedestroy($src);
        return [
            'binary' => $imageBinary,
            'extension' => 'png',
            'mime' => 'image/png',
            'optimized' => false
        ];
    }

    // Fond blanc pour les formats source avec transparence
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

    ob_start();
    $ok = false;
    $extension = 'jpg';
    $mime = 'image/jpeg';

    if (function_exists('imagewebp')) {
        $quality = (int) (getenv('OPENAI_IMAGE_WEBP_QUALITY') ?: ($_ENV['OPENAI_IMAGE_WEBP_QUALITY'] ?? 78));
        $quality = max(40, min(90, $quality));
        $ok = imagewebp($dst, null, $quality);
        $extension = 'webp';
        $mime = 'image/webp';
    } else {
        $quality = (int) (getenv('OPENAI_IMAGE_JPEG_QUALITY') ?: ($_ENV['OPENAI_IMAGE_JPEG_QUALITY'] ?? 82));
        $quality = max(50, min(92, $quality));
        $ok = imagejpeg($dst, null, $quality);
        $extension = 'jpg';
        $mime = 'image/jpeg';
    }

    $optimizedBinary = (string) ob_get_clean();

    imagedestroy($src);
    imagedestroy($dst);

    if (!$ok || $optimizedBinary === '') {
        return [
            'binary' => $imageBinary,
            'extension' => 'png',
            'mime' => 'image/png',
            'optimized' => false
        ];
    }

    return [
        'binary' => $optimizedBinary,
        'extension' => $extension,
        'mime' => $mime,
        'optimized' => true,
        'width' => $dstW,
        'height' => $dstH
    ];
}
