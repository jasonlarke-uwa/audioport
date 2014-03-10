<?php
class FileLock {
	private $_handle;
	private $_path;
	
	public function handle() { return $this->_handle; }
	public function path() { return $this->_path; }
	
	public function __construct($path, $handle) {
		$this->_path = $path;
		$this->_handle = $handle;
	}
	
	public static function aquire($filepath, $permissions, $type) {
		$handle = @fopen($filepath, $permissions);
		if (!$handle) { return NULL; }
		
		return flock($handle, $type) ? new FileLock($filepath, $handle) : NULL;
	}
	
	public function release() {
		if ($this->_handle !== NULL) {
			flock($this->_handle, LOCK_UN);
			fclose($this->_handle);
			$this->_handle = NULL;
			return TRUE;
		}
		return TRUE;
	}
	
	public function __destruct() {
		$this->release();
	}
};

class FileCache {
	private $_directory;
	
	public function __construct($cachedir) {
		$this->_directory = $cachedir;
	}
	
	public function getReadOnly($filepath) {
		return FileLock::aquire(self::combinePaths($this->_directory, $filepath), 'rb', LOCK_SH);
	}
	
	public function getWriteNew($filepath) {
		//return FileLock::aquire("data/cache/74293541.mp3", 'w', LOCK_EX);
		return FileLock::aquire(self::combinePaths($this->_directory, $filepath), 'w+b', LOCK_EX);
	}
	
	private static function combinePaths($path1, $path2) {
		$path1 = rtrim($path1, '/\\');
		$path2 = ltrim($path2, '/\\');
		
		return "{$path1}/{$path2}";
	}
};
?>