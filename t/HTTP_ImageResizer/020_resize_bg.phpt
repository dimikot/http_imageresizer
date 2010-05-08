--TEST--
HTTP_UrlSigner: resize with background
--FILE--
<?php
require dirname(__FILE__) . '/init.php';

$im = getResizedIm(array("name" => "m2.jpg", "w" => 100, "h" => 100, "bg" => "#FF0000", "quality" => 9));
echo imagesx($im) . " x " . imagesy($im) . "\n";
echo roundColor(imagecolorat($im, 50, 0)) . "\n";
echo roundColor(imagecolorat($im, 99, 50)) . "\n";
echo roundColor(imagecolorat($im, 50, 99)) . "\n";
echo roundColor(imagecolorat($im, 0, 50)) . "\n";
echo roundColor(imagecolorat($im, 50, 50)) . "\n";
?>

--EXPECT--
100 x 100
F00000
00F000
F00000
00F000
00F000

