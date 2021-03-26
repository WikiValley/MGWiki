<?php
header('Content-type: image/png');
include('ImageFunctions.php');

$img = imagecreatefrompng("menu-list-background.png");

$default = '#a8d7f9';
if ( isset( $_GET['color'] ) && strlen($_GET['color']) == 6 && preg_match('/[a-z0-9]{6}/', $_GET['color'] ) == 1 ) { //
  $default = '#' . $_GET['color'];
}
$r = hexToRGB($default)[0];
$g = hexToRGB($default)[1];
$b = hexToRGB($default)[2];
$color = imagecolorallocate($img, $r, $g, $b);

ImageSetPixel ($img, 0, 99, $color);
imagepng($img);
?>
