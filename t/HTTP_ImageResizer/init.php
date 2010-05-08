<?php
header("Content-type: text/plain");
chdir(dirname(__FILE__));
include_once "../../lib/config.php";
include_once "HTTP/ImageResizer.php";

$signer = new HTTP_UrlSigner('some-secret-code', "http://example.com/file/*?abc");
$resizer = new HTTP_ImageResizer($signer, "imageGetter");

function roundColor($color)
{
	$color = sprintf("%06x", $color);
	$rgb = sscanf($color, '%2x%2x%2x');
	foreach ($rgb as $c) {
		echo sprintf("%02X", $c & 0xF0);
	}
}

function getResizedIm($params)
{
	global $resizer;
	list ($mime, $data) = $resizer->getResize($params);
	return imagecreatefromstring($data);
}

function imageGetter(array $params)
{
	return file_get_contents("fixture/" . $params['name']);
}
