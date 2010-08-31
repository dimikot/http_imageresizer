<?php
require_once "HTTP/UrlSigner.php";

/**
 * Resize images "on the fly" or builds a signed URL for an image resize.
 * You MUST use outer caching system (e.g. nginx) to deal with performance.
 * 
 * @version 1.02
 */
class HTTP_ImageResizer
{
	private $_urlSigner;
	private $_dataGetter;
	
	/**
	 * Create a new ImageResizer object.
	 *
	 * @param HTTP_UrlSigner $urlSigner
	 * @param callback $dataGetter
	 */
	public function __construct(HTTP_UrlSigner $urlSigner, $dataGetter)
	{
		$this->_urlSigner = $urlSigner;
		$this->_dataGetter = $dataGetter;
	}
	
	/**
	 * Front controller method. 
	 * Process the request and prints resulting image.
	 *
	 * @param string $requestUrl    URL of the current script. May be absolute or relative.
	 * @return void  Never returns.
	 */
	public function main($requestUrl)
	{
		try {
			$params = $this->_urlSigner->parseUrl($requestUrl);
		} catch (Exception $e) {
			$this->_error($e->getMessage());
			return;
		}
		list($mime, $data) = $this->getResize($params);
		header("Content-Type: $mime");
		// Cache forever, because file content never changes without ID change.
		// Use constant Last-Modified date in the past to ensure that it is NEVER changed
		// (seems WebKit has an unstable bug when it processes Not Modified response
		// status which has different Last-Modified than the original cached content). 
		header("Last-Modified: " . gmdate("D, d M Y H:i:s", 3600 * 24 * 365) . " GMT");
		header("Expires: Wed, 08 Jul 2037 22:53:52 GMT");
		header("Cache-Control: public");
		echo $data;
	}
	
	/**
	 * Builds URL with data is token mixed in.
	 * 
	 * Input array may contain keys:
	 * - "w": width of the new image (required) 
	 * - "h": height of the new image (required)
	 * - "bg": background color in HTML-format (if present, resulting image always 
	 *   has the size of w*h, and original image is drawed in the center)
	 * - "format": gif | jpeg | png (by default - png)
	 * - "quality": jpeg or png quality (optional)
	 *
	 * @param array $params
	 * @return string
	 */
	public function getUrl(array $params)
	{
		assert('isset($params["w"])');
		assert('isset($params["h"])');
		return $this->_urlSigner->buildUrl($params);
	}

	/**
	 * Return resized image data.
	 *
	 * @param array $params
	 * @return array   array($mime, $data)
	 */
	public function getResize(array $params)
	{
		$data = call_user_func($this->_dataGetter, $params);
		list ($mime, $data) = $this->_resize($data, $params);
		return array($mime, $data);
	}
	
	/**
	 * Return URL signer object.
	 *
	 * @return HTTP_UrlSigner
	 */
	public function getSigner()
	{
		return $this->_signer;
	}
	
	/**
	 * Called on error.
	 *
	 * @param string $msg
	 */
	protected function _error($msg)
	{
		header("HTTP/1.1 404 Not Found");
		echo $msg;
	}
	
	private function _resize($data, array $params)
	{
		list($w, $h, $mime) = $this->_getImageSize($data);
		
		$maxW = $params['w'];
		$maxH = $params['h'];
		
		if (!$maxW || $maxW > $w) $maxW = $w;
		if (!$maxH || $maxH > $h) $maxH = $h;
				
		if ($w <= $maxW && $h <= $maxH) {
			return array($mime, $data);
		}
		
		$im = imagecreatefromstring($data);
//		imagefilledpolygon($im, array(80,50, 500,50, 500,125, 80,125), 4, imagecolorallocate($im, 240,240,240));
		$newW = $w;
		$newH = $h;
		
		if ($newW > $maxW || $newH > $maxH) {
		    if ($newW > $maxW && $newH > $maxH) {
		        $newW = $maxW;
		        $newH = $h * ($newW / $w);
        		if ($newH > $maxH) {
        			$newH = $maxH;
    		        $newW = $w * ($newH / $h);
        		}
		    } elseif ($newW > $maxW) {
		        $newW = $maxW;
    			$newH = $newW / ($w / $h);
		    } else {
		        $newW = $newH / ($h / $w);
    			$newH = $maxH;
		    }
		}
		
		$newW = round($newW);
		$newH = round($newH);
		
//		imagestring($im, 5, 90, 60, $maxW . ' x ' . $maxH, imagecolorallocate($im, 255,0,0));
//		imagestring($im, 5, 90, 100, round($newW) . ' x ' . round($newH), imagecolorallocate($im, 0,0,255));
		if (strlen(@$params['bg'])) {
			$newIm = imagecreatetruecolor(max($newW, $params['w']), max($newH, $params['h']));
			$rgb = sscanf(preg_replace('/#/', '', $params['bg']), '%2x%2x%2x');
			$color = imagecolorallocate($newIm, $rgb[0], $rgb[1], $rgb[2]);
			imagefilledrectangle($newIm, 0, 0, imagesx($newIm) - 1, imagesy($newIm) - 1, $color);
			$x = (imagesx($newIm) - $newW) / 2;
			$y = (imagesy($newIm) - $newH) / 2;
		} else {
			$newIm = imagecreatetruecolor($newW, $newH);
			$x = $y = 0;
		}
		
		$format = isset($params['format'])? $params['format'] : 'png';
		$func = "image" . $format;
		
		imagecopyresampled($newIm, $im, $x, $y, 0, 0, $newW, $newH, $w, $h);
		ob_start();
		if (isset($params['quality']) && $params['quality'] >= 0 && ($format == "jpeg" && $params['quality'] <= 100 || $format == "png" && $params['quality'] <= 9)) {
			$func($newIm, null, $params['quality']);
		} else {
			$func($newIm);
		}
		$data = ob_get_clean();
		
		return array("image/$format", $data);
	}
	
	private function _getImageSize($data)
	{
		$tmp = tempnam(sys_get_temp_dir(), 'ir');
		file_put_contents($tmp, $data); // Unfortunately for jpeg - we HAVE to save ALL data, else we cannot detect image size.
		list ($w, $h, $type) = getimagesize($tmp);
		$mime = image_type_to_mime_type($type);
		unlink($tmp);
		return array($w, $h, $mime);
	}
	
	private function _getUriByUrl($url)
	{
		$parsed = parse_url($url);
		return $parsed['path'] . (@$parsed['query']? '?' . $parsed['query'] : "");
	}
}
