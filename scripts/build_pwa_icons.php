<?php

/**
 * Genera iconos PWA a partir de laravel/public/brand/logo.png.
 * Usage: php scripts/build_pwa_icons.php
 */
$srcPath = __DIR__ . '/../laravel/public/brand/logo.png';
$outDir = __DIR__ . '/../laravel/public';

$src = imagecreatefrompng($srcPath);
if (!$src) { fwrite(STDERR, "Cannot load logo.png\n"); exit(1); }

$srcW = imagesx($src);
$srcH = imagesy($src);
$aspect = $srcW / $srcH;

$bg = [0x43, 0x38, 0xca]; // indigo-700

function makeIcon($src, int $size, array $bg): \GdImage {
    $out = imagecreatetruecolor($size, $size);
    imagealphablending($out, true);
    // background indigo
    $bgColor = imagecolorallocate($out, $bg[0], $bg[1], $bg[2]);
    imagefilledrectangle($out, 0, 0, $size, $size, $bgColor);

    $targetW = (int) round($size * 0.72);
    $targetH = (int) round($targetW / $GLOBALS['aspect']);

    $srcAspect = $GLOBALS['srcW'] / $GLOBALS['srcH'];
    if ($targetW / $targetH > $srcAspect) {
        $targetW = (int) round($targetH * $srcAspect);
    } else {
        $targetH = (int) round($targetW / $srcAspect);
    }

    $dstX = (int) floor(($size - $targetW) / 2);
    $dstY = (int) floor(($size - $targetH) / 2);

    imagecopyresampled($out, $src, $dstX, $dstY, 0, 0, $targetW, $targetH, $GLOBALS['srcW'], $GLOBALS['srcH']);

    return $out;
}

$sizes = [512, 192, 180, 128, 48, 32, 16];
$GLOBALS['aspect'] = $aspect;
$GLOBALS['srcW'] = $srcW;
$GLOBALS['srcH'] = $srcH;

foreach ($sizes as $size) {
    $icon = makeIcon($src, $size, $bg);
    $path = $outDir . '/pwa-' . $size . '.png';
    imagepng($icon, $path);
    imagedestroy($icon);
    echo "wrote $path ($size)\n";
}

// apple-touch-icon (180) -> apple-touch-icon.png
$apple = makeIcon($src, 180, $bg);
imagepng($apple, $outDir . '/apple-touch-icon.png');
imagedestroy($apple);
echo "wrote $outDir/apple-touch-icon.png (180)\n";

// android-chrome aliases
@copy($outDir . '/pwa-512.png', $outDir . '/android-chrome-512x512.png');
@copy($outDir . '/pwa-192.png', $outDir . '/android-chrome-192x192.png');
echo "wrote android-chrome-512x512.png + android-chrome-192x192.png\n";

imagedestroy($src);
echo "DONE\n";
