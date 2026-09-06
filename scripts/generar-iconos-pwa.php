<?php

declare(strict_types=1);

$directorio = dirname(__DIR__).'/public/icons';
if (! is_dir($directorio) && ! mkdir($directorio, 0755, true) && ! is_dir($directorio)) {
    fwrite(STDERR, "No se pudo crear {$directorio}\n");
    exit(1);
}

$generar = static function (int $size, string $archivo, bool $maskable) use ($directorio): void {
    $im = imagecreatetruecolor($size, $size);
    imagesavealpha($im, true);
    $transparente = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagefill($im, 0, 0, $transparente);

    $azul = imagecolorallocate($im, 49, 91, 234);
    $azulOscuro = imagecolorallocate($im, 23, 37, 84);
    $blanco = imagecolorallocate($im, 255, 255, 255);
    $verde = imagecolorallocate($im, 115, 228, 177);

    imagefilledrectangle($im, 0, 0, $size, $size, $azul);

    $margen = (int) round($size * ($maskable ? 0.22 : 0.16));
    $caja = $size - (2 * $margen);
    imagefilledellipse($im, (int) ($size / 2), (int) ($size / 2), $caja, $caja, $azulOscuro);

    $baseX = (int) round($size * ($maskable ? 0.34 : 0.30));
    $anchoBarra = (int) round($size * 0.10);
    $hueco = (int) round($size * 0.06);
    $baseY = (int) round($size * ($maskable ? 0.70 : 0.72));
    $alturas = [
        (int) round($size * 0.18),
        (int) round($size * 0.28),
        (int) round($size * 0.38),
    ];

    foreach ($alturas as $indice => $altura) {
        $x = $baseX + ($indice * ($anchoBarra + $hueco));
        $color = $indice === 2 ? $verde : $blanco;
        imagefilledrectangle($im, $x, $baseY - $altura, $x + $anchoBarra, $baseY, $color);
    }

    $ruta = $directorio.'/'.$archivo;
    imagepng($im, $ruta, 9);
    imagedestroy($im);
    echo "OK {$ruta}\n";
};

$generar(180, 'apple-touch-icon.png', false);
$generar(192, 'icon-192.png', false);
$generar(512, 'icon-512.png', false);
$generar(192, 'icon-192-maskable.png', true);
$generar(512, 'icon-512-maskable.png', true);
