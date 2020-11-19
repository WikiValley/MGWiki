<?php
header('Content-type: image/png');
include('ImageFunctions.php');

$img = imagecreatetruecolor(1,46);
$blanc = '#fcfcfc';
$default = '#a8d7f9';

if ( isset( $_GET['color'] ) && strlen($_GET['color']) == 6 && preg_match('/[a-z0-9]{6}/', $_GET['color'] ) == 1 ) { //
  $default = '#' . $_GET['color'];
}

// DEBUG:
//$noir = imagecolorallocate($img, 0, 0, 0);
//imagestring($img, 5, 4, 4, $_GET['color'], $noir);

$img = degrade($img,'v',hexToRGB($blanc),hexToRGB($default));
imagepng($img);

?>
