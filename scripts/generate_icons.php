<?php
/**
 * Generate PWA icons from SVG
 * Run once: php scripts/generate_icons.php
 * Requires: Imagick or GD extension
 */

$svgPath = __DIR__ . '/../icons/icon.svg';
$iconDir = __DIR__ . '/../icons/';
$sizes = [72, 96, 128, 144, 152, 192, 384, 512];

if (!is_dir($iconDir)) mkdir($iconDir, 0755, true);

if (extension_loaded('imagick')) {
    $svg = new Imagick();
    $svg->setBackgroundColor(new ImagickPixel('transparent'));
    $svg->readImageBlob(file_get_contents($svgPath));
    $svg->setImageFormat('png');

    foreach ($sizes as $size) {
        $icon = clone $svg;
        $icon->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1);
        $icon->writeImage($iconDir . 'icon-' . $size . '.png');
        echo "Generated icon-{$size}.png\n";
    }
    echo "Done! All icons generated.\n";

} elseif (extension_loaded('gd')) {
    foreach ($sizes as $size) {
        $img = imagecreatetruecolor($size, $size);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $bg = imagecolorallocate($img, 10, 20, 32);
        imagefilledrectangle($img, 0, 0, $size, $size, $bg);
        $gold = imagecolorallocate($img, 212, 168, 75);
        imagestring($img, 5, (int)($size/2 - 10), (int)($size/2 - 10), 'AK', $gold);
        imagepng($img, $iconDir . 'icon-' . $size . '.png');
        imagedestroy($img);
        echo "Generated icon-{$size}.png (GD)\n";
    }
    echo "Done! Note: Install Imagick for better quality icons.\n";

} else {
    echo "ERROR: Neither Imagick nor GD available.\n";
}
