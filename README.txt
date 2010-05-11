HTTP_ImageResizer: Load and resize images from non-file sources with nginx caching.
(C) Dmitry Koterov, http://en.dklab.ru/lib/HTTP_ImageResizer/

This library is used to load static image content from anywhere (e.g. from a 
database, from remote storage etc.), resize it and return to browser. Fetching
and resizing is performed "on demand": images are resized when a request
arrives, but resized result is stored to nginx (or similar) cache, so there 
is no performance bottleneck.

1. Build an image URL
---------------------

$signer = new HTTP_UrlSigner("very-secret-word",'http://example.com/image.php?*');
$resizer = new HTTP_ImageResizer($signer, null);
$paramsToPassToProcessor = array(
    "w"       => 20, 
    "h"       => 30,
    "format"  => "jpeg",
    "bg"      => "00FF00",
    "quality" => 90,
    // Everything else are custom parameters:
    "id"      => 10,
    "any"     => "other",
);
echo '<img src="' . $resizer->getUrl($paramsToPassToProcessor) . '"/>';
   
Result looks like: <img src="http://example.com/image.php?af0b386b9dc43dc0..." />
Passed parameters is encoded, signed and accessible in image.php processor.
   

2. Process an image request at image.php
----------------------------------------

<?php
...
$signer = new HTTP_UrlSigner("very-secret-word",'http://example.com/image.php?*');
$resizer = new HTTP_ImageResizer($signer, "callbackImageRawContent");
$resizer->main($_SERVER['REQUEST_URI']);
...
function callbackImageRawContent($paramsPassedToProcessor) 
{
    // $paramsPassedToProcessor["id"] and $paramsPassedToProcessor["other"]
    // are accessible here.
    return DB::selectCell(
        "SELECT raw FROM image WHERE id=?", 
        $paramsPassedToProcessor['id']
    );
    // ...or similar
}

3. Configure nginx caching (very important!)
--------------------------------------------

fastcgi_cache_path /var/cache/nginx levels= keys_zone=cache:10m;
...
location /image.php {
    fastcgi_cache cache;
    fastcgi_cache_valid 200 304 404 240h;
    fastcgi_cache_key "method=$request_method|ims=$http_if_modified_since|inm=$http_if_none_match|host=$host|uri=$request_uri";
    fastcgi_hide_header "Set-Cookie";
    fastcgi_ignore_headers "Cache-Control" "Expires";
    ...
    # or use proxy_* commands if you use Apache, not FastCGI PHP
}

If you do not configure nginx (or similar) caching, resize process will be
executed on each request, it is VERY slow! So be sure to use

ab -n 10000 -c 10 http://example.com/image.php?af0b386b9dc43dc0

after configuring nginx to test that caching works correctly.
