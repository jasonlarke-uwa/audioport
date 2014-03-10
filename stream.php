<?php
set_time_limit(0);

function getRemoteFileSize($url) {
	 $ch = curl_init($url);

	 curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	 curl_setopt($ch, CURLOPT_HEADER, TRUE);
	 curl_setopt($ch, CURLOPT_NOBODY, TRUE);

	 $data = curl_exec($ch);

	 $size = (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) 
		? curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD)
		: false;

	 curl_close($ch);
	 return $size;
}

function serveContent($id, $url) {
	//$cache = new FileCache("protected/data/cache");
	//$size = getRemoteFileSize($url);
	//header("Content-Length: {$size}");
	
	$remote = fopen($url, 'r');
	if ($remote) {
		while(!feof($remote)) {
			echo fread($remote, 4096);
		}
		fclose($remote);
	}
	else {
		header("HTTP/1.0 404 Not Found", true, 404);
	}
	
	/* Removed caching code for now
	if (($lck = $cache->getReadOnly("{$id}.mp3")) !== NULL && ($size == filesize($lck->path()))) {
		if (function_exists("apache_get_modules")) {
			$modules = apache_get_modules();
			if (in_array("mod_xsendfile", $modules)) {
				header("X-Sendfile: {$lck->path()}");
				$lck->release();
				return;
			}
		}
		
		header("Content-Length: {$size}", true);
		readfile($lck->path());
		$lck->release();
	}
	else if (($lck = $cache->getWriteNew("{$id}.mp3")) !== NULL) {
		header("Content-Length: {$size}");

		$remote = fopen($url, 'r');
		
		while(!feof($remote)) {
			$data = fread($remote, 8192);
			fwrite($lck->handle(), $data);
			echo $data;
		}
		
		$lck->release();
		fclose($remote);
	}
	else {
		header("Content-Length: {$size}");
		readfile($url);
	}*/
}

function ends_with($str, $substr) {
	return (($length = strlen($substr)) == 0 ? true : substr($str, -$length) === $substr);
}
	
$_GET['src'] = empty($_GET['src']) ? '' : trim($_GET['src']);
$_GET['name'] = empty($_GET['name']) ? '' : trim($_GET['name']);
	
if (!empty($_GET['id']) && ($url = parse_url($_GET['src'])) !== false && ends_with($url['host'], 'vk.me') && ends_with($url['path'], '.mp3')) {
	$expires = 60*60*24*365;

	header("Pragma: public");
	header("Cache-Control: public, must-revalidate, max-age={$expires}");
	header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$expires) . ' GMT');
	header('Content-Type: audio/mpeg');
	
	if (!empty($_GET['rel']) && strtolower($_GET['rel']) === 'download') {
		$filename = preg_replace("/[^\w\s\d\-_~,;:\[\]\(\]]|[\.]{2,}/", "", $_GET['name']);
		header("Content-Disposition: attachment; filename=\"{$filename}.mp3\"");
	}

	serveContent(intval($_GET['id']), $_GET['src']);
}
else {
	header("HTTP/1.0 403 Forbidden", true, 403);
}
?>