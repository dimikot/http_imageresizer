--TEST--
HTTP_UrlSigner: main()
--FILE--
<?php
require dirname(__FILE__) . '/init.php';

$resizer->main("aaa");
?>

--EXPECT--
URL does not match the mask "http://example.com/file/*?abc"
