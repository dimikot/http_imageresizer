--TEST--
HTTP_UrlSigner: resize to fit width
--FILE--
<?php
require dirname(__FILE__) . '/init.php';

$im = getResizedIm(array("name" => "m2.jpg", "w" => 100, "h" => 100));
echo imagesx($im) . " x " . imagesy($im) . "\n";
echo roundColor(imagecolorat($im, 50, 20)) . "\n";
echo roundColor(imagecolorat($im, 99, 20)) . "\n";
echo roundColor(imagecolorat($im, 50, 40)) . "\n";
echo roundColor(imagecolorat($im, 0, 20)) . "\n";
?>

--EXPECT--
100 x 41
00F000
00F000
00F000
00F000
