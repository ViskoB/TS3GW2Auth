<?php
$text = '<unique string>';

if ($_GET['text'] && $_GET['text'] != '') {
    $text = $_GET['text'];
}

$type = 'key_create';

if (substr($text, 0, 5) != 'GHTS3') {
    $type = 'poke';
}

$CopyOrCreate = 'create';

if ($_GET['copy'] && $_GET['copy'] == 1) {
    $CopyOrCreate = 'copy';
}

//Set the Content Type
header('Content-type: image/png');

// Create Image From Existing File
if ($type == 'key_create') {
    if ($CopyOrCreate == 'create') {
        $png_image = imagecreatefrompng('create_key.png');
    } else {
        $png_image = imagecreatefrompng('copy_key.png');
    }
} else {
    $png_image = imagecreatefrompng('bot_poke.png');
}

// Allocate A Color For The Text
$font_col = imagecolorallocate($png_image, 0, 0, 0);

// Set Path to Font File
if ($type == 'key_create') {
    $font_path = 'Cronos-Pro.ttf';
} else {
    $font_path = 'SEGOEUIB.TTF';
}

// Print Text On Image
if ($type == 'key_create') {
    if ($CopyOrCreate == 'create') {
        imagettftext($png_image, 14, 0, 25, 200, $font_col, $font_path, $text);
    } else {
        imagettftext($png_image, 14, 0, 10, 27, $font_col, $font_path, $text);
    }
} else {
    imagettftext($png_image, 9, 0, 18, 50, $font_col, $font_path, $text);
}

// Send Image to Browser
imagepng($png_image);

// Clear Memory
imagedestroy($png_image);
?> 