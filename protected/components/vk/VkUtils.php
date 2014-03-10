<?php
class VkUtils {
	const VK_SESSION_PREFIX = "remixsid";
	
	// Very, very trivial cookie parsing method. Isn't standard compliant at all, but works
	// fine in this situation.
	public static function parse_cookie($cookieString) {
		$csplit = explode(';', trim($cookieString));
		$cdata = array();
		$reserved = array('domain','expires','path','secure','comment','httponly');

		foreach($csplit as $data) {
			$cinfo = explode('=', $data, 2);
			$cinfo[0] = trim($cinfo[0]);
			
			$clower = strtolower($cinfo[0]);
			if (in_array($clower, $reserved)) {
				switch($clower) {
				case 'secure':
				case 'httponly':
					$cinfo[1] = 'true'; break;
				case 'expires':
					$cinfo[1] = strtotime(trim($cinfo[1])); break;
				default: break;
				}
				
				$cdata[trim($cinfo[0])] = $cinfo[1];
			}
			else {
				$cdata['cookies'][urldecode($cinfo[0])] = urldecode($cinfo[1]);
			}
		}

		return $cdata;
	}
	
	public static function extract_cookies($httpHeaders) {
		$cookies = array();
		preg_match_all('~^Set-Cookie:\\s+([^\\r\\n]+)~im', $httpHeaders, $matches);
		foreach($matches[1] as $match) {
			$cookies[] = self::parse_cookie($match);
		}
		return $cookies;
	}
	
	public static function build_cookie_string($cookies) {
		$collection = array();
		foreach($cookies as $cookie) {
			foreach($cookie['cookies'] as $name=>$value) {
				$collection[] = urlencode($name) . '=' . urlencode($value);
			}
		}
		return implode('; ', $collection);
	}
	
	public static function extract_session($cookieCollection) {
		$session = null;
		$key = null;
		
		foreach($cookieCollection as $cookie) {
			foreach($cookie['cookies'] as $name=>$value) {
				if (strpos($name,self::VK_SESSION_PREFIX) === 0) {
					$session = $cookie;
					$key = $name;
				}
			}
		}
		
		return $session ? array(
			'id' => $session['cookies'][$key],
			'key' => $key,
			'expires' => $session['expires']
		) : null;
	}
	
	public static function curl_exec_follow($ch, $max=5) {
		$response = false;
		
		// Common cURL options that need to be turned on for this to work and return consistently
		curl_setopt($ch, CURLOPT_HEADER,true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
			
		if (ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off')) {
			// Can just use FOLLOWLOCATION, still need to parse the headers/status/content though.
			// to maintain a consistent return.
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_MAXREDIRS, $max);
			$response = curl_exec($ch);
			
			if ($response !== false) {
				$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
				$response = array(
					'headers' => substr($response, 0, $headerSize),
					'content' => substr($response, $headerSize),
					'status' => curl_getinfo($ch, CURLINFO_HTTP_CODE)
				);
			}
		} 
		else {
			// explicitly turn FOLLOWLOCATION off, just in case servers do weird things.
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
			// use a local copy of the cURL handle.
			$local = curl_copy_handle($ch);
			$redirected = false;
			
			$target = curl_getinfo($local, CURLINFO_EFFECTIVE_URL);
			$last = parse_url($target); // need to maintain a reference to the last redirect in case following redirects are relative paths.
			if (empty($last['scheme']) || empty($last['hostname'])) {
				// relative path on the server, very trivial attempt to build a correct path.
				$last = parse_url("http" . (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 's://' : '://') . $_SERVER['HTTP_HOST'] . $target);
			}
			
			$redirectCodes = array(301,302,307); // no support for 305 proxy redirects...yet :)
			$headers = array(); // maintain the headers throughout the redirection.
			
			do {
				curl_setopt($local, CURLOPT_URL, $target);
				if (($response = curl_exec($local)) === false) {
					break;
				}
				
				$code = curl_getinfo($local, CURLINFO_HTTP_CODE);
				$headerSize = curl_getinfo($local, CURLINFO_HEADER_SIZE);
				$header = substr($response, 0, $headerSize);
				$headers[] = rtrim($header);
				$redirected = in_array($code, $redirectCodes);
				
				if ($redirected) {
					if (preg_match('/^(?:Location|URI):\\s*([^\\r\\n]+)/m', $header, $matches) !== 1) {
						trigger_error("Requested resource is redirecting incorrectly.", E_USER_WARNING);
						$redirected = false; // set the loop-breaking condition.
						$response = false; // invalidate the response
					}
					
					$target = parse_url(trim($matches[1]));
					if (empty($target['scheme'])) { // relative redirect, use the last url
						$target = $last['scheme'] . '://' . $last['host'] . $target['path'];
						$last = parse_url($target);
					}
					elseif ($target['scheme'] === 'file') {
						// Redirecting into the file:// protocol, can be unsafe
						trigger_error("Requested resource is attempting to redirect to files on the local system.", E_USER_WARNING);
						$redirected = false; // set the loop-breaking condition.
						$response = false; // invalidate the response
					}
					else {
						$target = trim($matches[1]);
						$last = parse_url($target);
					}
					
					if ($code === 302) {
						// Many browsers implemented the 302-redirect in this way. i.e subsequent redirects
						// used the GET method, even when the original method used POST. 
						// See http://en.wikipedia.org/wiki/HTTP_302 for more information.
						curl_setopt($local, CURLOPT_HTTPGET, true);
					}	
				}
			} while($redirected && --$max >= 0);

			if ($response !== false) {
				$response = array(
					'headers' => implode("\r\n\r\n", $headers), // standard is an CRLF seperator between headers as far as I know.
					'content' => substr($response, curl_getinfo($local, CURLINFO_HEADER_SIZE)),
					'status' => curl_getinfo($local, CURLINFO_HTTP_CODE)
				);
			}
			curl_close($local);
		}
		return $response;
	}
}
?>