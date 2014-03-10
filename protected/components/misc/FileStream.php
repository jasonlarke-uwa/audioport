<?php
class FileStream {
	public $_stream;
	private $_position;
	private $_disposed;
	
	public function position() { return $this->_position; }
	
	public function __construct($baseStream) {
		$this->_stream = $baseStream;
		$this->_position = ftell($this->_stream);
		if ($this->_position === false) {
			$this->_position = 0;
		}	
	}
	
	public function read($count) {
			return $this->drain($count);
	}
	
	public function read8() {
		$data = $this->drain(1);
		return $data !== FALSE 
			? current(unpack('C', $data))
			: FALSE;
	}
	
	public function read16() {
		$data = $this->drain(2);
		return $data !== FALSE 
			? current(unpack('n', $data))
			: FALSE;
	}
	
	public function read24() {
		$data = $this->drain(3);
		return $data !== FALSE 
			? current(unpack('N',  "\0" . $data))
			: FALSE;
	}
	
	public function read32() {
		$data = $this->drain(4);
		return $data !== FALSE
			? current(unpack('N', $data))
			: FALSE;
	}
	
	public function drain($count) {
		$buffer = "";
		$chunk = "";
		$total = 0;
		$read = 0;
		
		while ($total < $count && ($chunk = fread($this->_stream, $count - $total)) !== false) {
			$read = strlen($chunk);
			$total += $read;
			$this->_position += $read;
			$buffer .= $chunk;
		}
		
		return $count == $total ? $buffer : false;
	}
	
	public function seek($offset, $whence) {
		$value = fseek($this->_stream, $offset, $whence);
		if ($value === 0) {
			$this->_position = ($whence === SEEK_SET
				? $offset
				: ($whence === SEEK_CUR
					? $this->_position + $offset
					: ftell($this->_stream)
				)
			);	
		}
		
		return $value;
	}
	
	public function drain_seek($offset, $whence) {
		if (($whence === SEEK_END) || ($whence === SEEK_SET && $offset < $this->_position) || ($whence === SEEK_CUR && $offset <= 0)) {
			return -1;
		}
		
		$forward = $whence === SEEK_SET ? ($offset - $this->_position) : $offset;
		if ($forward > 0) {
			$chunk = "";
			$total = 0;
			$read = 0;
			
			while ($total < $forward && ($chunk = fread($this->_stream, $forward - $total)) !== false) {
				$read = strlen($chunk);
				$total += $read;
				$this->_position += $read;
			}
		}
		return 0;
	}
	
	public function dispose() {
		if (!$this->_disposed) {
			fclose($this->_stream);
			$this->_position = -1;
			$this->_disposed = true;
		}
	}
};