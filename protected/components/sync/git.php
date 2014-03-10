<?php
class GitUser {
	private $_username;
	private $_password;
	
	public function username() { return $this->_username; }
	public function password() { return $this->_password; }
	
	public function __construct($username,$password) {
		$this->_username = $username;
		$this->_password = $password;
	}
}

class GitRepository {
	private $_name;
	private $_url;
	
	public function __construct($name, $url) {
		$this->_name = $name;
		$this->_url = $url;
	}
	
	public function downloadFileFromBranch($file, $branch, $destination, $user=null) {
		$fh = fopen($destination, "w+");
		if ($fh) {
			$url = "{$this->_url}/raw/{$branch}/{$file}";
			$handle = $this->createRequest($url, $user);
			
			curl_setopt($handle, CURLOPT_FILE, $fh);
			if(!curl_exec($handle))
				var_dump(curl_error($handle));
			curl_close($handle);
			fclose($fh);

			return TRUE;
		}
		return FALSE;
	}
	
	private function createRequest($url, $user) {
		$handle = curl_init($url);
		curl_setopt($handle, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, FALSE);
		
		if ($user) {
			$u = urlencode($user->username());
			$p = urlencode($user->password());
			curl_setopt($handle, CURLOPT_USERPWD, "{$u}:{$p}");
		}
		
		return $handle;
	}
}

class GitLock {
	private $_path;
	
	public function __construct($path) {
		$this->_path = rtrim($path, '\\/');
	}
	
	public function release() {
		self::rmrf($this->_path);
	}	
	
	private static function rmrf($path) {
		$me = array('GitLock', 'rmrf');
		return is_file($path) 
			? @unlink($path) 
			: array_map($me, glob($path.'/*')) == @rmdir($path);
	}
	
	public function getTempLocation() {
		return $this->_path;
	}
}

class GitSync {
	private static $_syncDir = 'tmp/sync';
	private static $_syncFormat = '%t.sync';

	public static function aquireLock() {
		$hash = hash('sha256', microtime());
		$filepath = self::getSyncRoot() . '/' . str_replace('%t', $hash, self::$_syncFormat);
		mkdir($filepath, 0700, true);
		
		$ss = self::getSnapshot();
		$hasLock = false;

		while(!$hasLock) {
			$hasLock = true;
			foreach($ss as $s) {
				if ($s !== $filepath && file_exists($s)) {
					$hasLock = false;
					// Sleep for 1 second each fail pass to avoid consuming too many resources while waiting for other syncs to complete
					sleep(1);
					break;
				}
			}
		}
	
		return new GitLock($filepath);
	}
	
	private static function getSyncRoot() {
		return rtrim(self::$_syncDir, '\\/');
	}
	
	private static function getSnapshot() {
		return glob(self::getSyncRoot() . '/*.sync', GLOB_ONLYDIR);
	}
}
?>