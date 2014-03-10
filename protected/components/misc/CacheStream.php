<?php
require_once "FileCache.php";

class CacheStream {
	private $_cache;
	private $_lock;
	
	public function stream_open($path, $mode, $options, &$opened_path) {
		$parts = explode('.', preg_replace('~[^:]+://~', "", $path), 2);
		if (count($parts) != 2) { return false; }
		
		$this->_cache = new FileCache($parts[0]);
		$this->_lock = $this->_cache->getWriteNew($parts[1]);
		
		return $this->_lock != NULL;
	}
	
	public function stream_write($data) {
		if ($this->_lock) {
			fwrite($this->_lock->handle(), $data);
			echo $data;
		}
	}
	
	public function stream_close() {
		if ($this->_lock !== NULL) {
			$this->_lock->release();
			$this->_lock = NULL;
		}
	}
};
?>