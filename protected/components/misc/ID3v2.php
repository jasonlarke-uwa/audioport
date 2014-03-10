<?php
require_once "FileStream.php";

class ID3v2 {
	const ID3_TAG_HEADER_SIZE		= 10;
	const ID3_SIGNATURE 			= "ID3";
	const ID3_HAS_EXTENDED_HEADER	= 0x40;
	const ID3_USES_UNSYNC 			= 0x80;
	
	public static function decode_sync($sync) {
		return ($sync & 0xFF) |
			 ((($sync >> 8) & 0xFF) << 7) |
			 ((($sync >> 16) & 0xFF) << 14) |
			 ((($sync >> 24) & 0xFF) << 21);
	}
	
	public static function get_iterator($resourcePath) {
		error_reporting(E_ALL);
		if (($fs = fopen($resourcePath, 'rb')) === FALSE) {
			return NULL;
		}
		
		$canSeek = preg_match('&^https?://&', $resourcePath) === 0;
		$stream = new FileStream($fs);
		$header = ID3v2TagHeader::from_stream($stream);
		
		return $header !== NULL 
			? new ID3v2StreamIterator($header, $stream, $canSeek) 
			: NULL;
	}
}

class ID3v2TagHeader {
	private $_identifier=NULL;
	private $_majorVersion=0;
	private $_minorVersion=0;
	private $_flags=0;
	private $_size=0;
	
	public function identifier()	{ return $this->_identifier; }
	public function major()			{ return $this->_majorVersion; }
	public function minor() 		{ return $this->minorVersion; }
	public function flags() 		{ return $this->_flags; }
	public function size()			{ return $this->_size; }
	
	public function __construct($id, $maj, $min, $flags, $size) {
		$this->_identifier = $id;
		$this->_majorVersion = $maj;
		$this->_minorVersion = $min;
		$this->_flags = $flags;
		$this->_size = $size;
	}
	
	public static function from_stream($stream) {
		if (!($stream instanceof FileStream)) {
			throw new Exception('$stream argument is not a valid FileStream object.');
		}
		
		$id = $stream->read(3);
		
		if ($id !== ID3v2::ID3_SIGNATURE) {
			return NULL;
		}
		
		$major = $stream->read8();
		$minor = $stream->read8();
	
		// Latest version is v2.4.0, support will not extend beyond that.
		if ($major === false || $minor === false || $major > 4) {
			return NULL;
		}

		$flags = $stream->read8();
		$syncsize = $stream->read32();
		
		if ($flags === false || $syncsize === false || $syncsize & 0x80808080) {
			return NULL;
		}
		
		$size = ID3v2::decode_sync($syncsize);
		
		return new ID3v2TagHeader($id, $major, $minor, $flags, $size);
	}
};

class ID3v2FrameHeader {
	private $_frameId;
	private $_size;
	private $_flags; // v2.3+

	public function id() 	{ return $this->_frameId; }
	public function size()	{ return $this->_size; }
	public function flags() { return $this->_flags; }
	
	public function __construct($fid, $size, $flags=0) {
		$this->_frameId = $fid;
		$this->_size = $size;
		$this->_flags = $flags;
	}
};

class ID3v2StreamIterator implements Iterator {
	private $_header;
	private $_stream;
	private $_current;
	private $_position;
	private $_canSeek;
	private $_disposed;
	
	public function id3_version() { return $this->_header->major(); }

	public function __construct($header, $fstream, $canSeek=false) {
		if (!($fstream instanceof FileStream)) {
			throw new Exception('$fstream argument is not a valid FileStream object.');
		}
		
		$this->_header = $header;
		$this->_stream = $fstream;
		$this->_canSeek = $canSeek;
		$this->_current = NULL;
		$this->_position = -1;
		$this->_disposed = false;
	}
	
	private function init() {
		// Setup the file position
		$id3v = $this->_header->major();
		
		if ($id3v > 2 && $this->_header->flags() & ID3v2::ID3_HAS_EXTENDED_HEADER) {
			// need to seek past the extended header to reach the frames.
			$size = $id3v < 4 
				? $this->_stream->read32() // pre 2.4, non-syncsafe int (doesn't include the header itself) 
				: ID3v2::decode_sync($this->_stream->read32()) - 4; // 2.4, syncsafe. Includes header size.
			$this->_stream->read($size);
		}
	}
	
	public function rewind() {
		if ($this->_current !== NULL || $this->_stream->position() > ID3v2::ID3_TAG_HEADER_SIZE) {
			if (!$this->_canSeek) {
				throw new Exception("Iteration is forward-only; cannot re-iterate over the collection.");
			}
			else {
				$this->_stream->seek(ID3v2::ID3_TAG_HEADER_SIZE, SEEK_SET);
			}
		}

		$this->init();
		$this->_current = $this->read_frame();
		$this->_position = $this->_stream->position();
	}
	
	private function read_frame() {
		$id3v = $this->_header->major();

		$frameId = $this->_stream->read(($id3v < 3 ? 3 : 4));
		if (!ctype_alnum($frameId)) {
			return NULL;
		}
		
		$frameSize = $id3v < 3 
						? $this->_stream->read24()
						: ($id3v < 4 ? $this->_stream->read32() : ID3v2::decode_sync($this->_stream->read32()));
						
		if ($frameSize === false || ((($this->_header->size() + ID3v2::ID3_TAG_HEADER_SIZE) - $this->_stream->position()) < $frameSize)) {
			return NULL;
		}
		
		$flags = $id3v < 3 ? 0 : $this->_stream->read16();

		return new ID3v2FrameHeader($frameId, $frameSize, $flags);
	}

	public function valid() {
		return $this->_current !== NULL;
	}
	
	public function next() {
		if (!$this->valid()) {
			throw new Exception('Iterator is not valid');
		}
		
		if ($this->_stream->position() === $this->_position) { // need to seek past the data
			$t = $this->_canSeek 
				? $this->_stream->seek($this->_current->size(), SEEK_CUR) 
				: $this->_stream->drain_seek($this->_current->size(), SEEK_CUR);
		}
		
		$this->_current = $this->read_frame();
		$this->_position = $this->_stream->position();
	}
	
	public function key() {
		if (!$this->valid()) {
			throw new Exception('Iterator is not valid');
		}
		
		return $this->_current->id();
	}
	
	public function current() {
		return $this->_current;
	}
	
	public function data() {
		if (!$this->valid()) {
			throw new Exception('Iterator is not valid');
		}
		else if ($this->_stream->position() !== $this->_position) {
			if (!$this->canSeek) {
				throw new Exception("Data has already been read from the stream.");
			}
			else {
				$this->_stream->seek($this->_position, SEEK_SET);
			}
		}
		return $this->_stream->read($this->_current->size());
	}
	
	public function text() {
		$raw = $this->data();
		$enc = ord($raw[0]);
		$string = substr($raw, 1);
		switch($enc) {
			case 0:
				return trim(iconv("ISO-8859-1", "UTF-8", $string));
			case 1:
				return trim(iconv("UTF-16", "UTF-8", $string));
			default:
				return trim($string);
		}
	}
	
	public function dispose() {
		if (!$this->_disposed) {
			$this->_disposed = true;
			$this->_current = NULL;
			$this->_position = -1;
			$this->_stream->dispose();
			$this->_stream = NULL;
		}
	}
};
?>