<?php
class VkResponse {
	private $_version;
	private $_fileList;
	private $_langId;
	private $_langVers;
	private $_code;
	private $_answer;
	
	// Get the Version Number on the request
	public function version()		{ return $this->_version; }
	// Get the dependent file list for the request (usually stylesheets/javascript)
	public function files()			{ return $this->_fileList; }
	// Get the language id number (for internationalization)
	public function langid()		{ return $this->_langId; }
	// Language version for the above language id.
	public function langversion()	{ return $this->_langVers; }
	// Status code of the request, values are found in the VkResponseType class.
	public function code()			{ return $this->_code; }
	// Returns the array of "answers" to the request. Typically some HTML or a JSON string.
	public function answers()		{ return $this->_answer; }

	// ctor..
	public function __construct($v, $f, $lid, $lvers, $c, $a) {
		$this->_version = $v;
		$this->_fileList = $f;
		$this->_langId = $lid;
		$this->_langVers = $lvers;
		$this->_code = $c;
		$this->_answer = $a;
	}
	
	// Parse a VkResponse from a raw HTTP response string.
	public static function parse($response) {
		// This isn't hackery; for some reason this manual replace 
		// is done even by VK's own codebase.
		$response = str_replace(
			array('<!--', '-<>->'), 
			array('', '-->'), 
			$response
		);

		// Sections of the response are delimited by the <!> token, in a documented order.
		$queue = explode('<!>', $response);
		// ensure the response at least contains enough values to be consistent with the expected ordering.
		if (count($queue) < 6) { 
			return false;
		}
		
		$offset = -1;
		// Parse through the response, ordering should be consistent.
		$version = intval($queue[++$offset]);
		$files = $queue[++$offset];
		$langId = intval($queue[++$offset]);
		$langVers = intval($queue[++$offset]);
		$code = intval($queue[++$offset]);
		
		$code = in_array($code, VkResponseType::$VALUES) ? $code : VkResponseType::UNKNOWN;
		$answer = array_slice($queue, ++$offset);
		
		if ($langId === 0 || $langVers === 0 || $version === 0) {
			return false;
		}
		
		return new VkResponse(
			$version,
			$files,
			$langId,
			$langVers,
			$code,
			$answer
		);
	}
}

// Current known VK response codes
class VkResponseType {
	const UNKNOWN = -2; // not actually a VK value, but my own placeholder in case VK adds more responses.
	const OK = 0;
	const EMAIL_NOT_CONFIRMED = 1;
	const CAPTCHA = 2;
	const ERROR = 8;
	const RELOAD = 5;
	const AUTH_FAILED = 3;
	const REDIRECT = 4;
	const MOBILE_VALIDATION_REQUIRED = 11;
	const MOBILE_VALIDATION_REQUIRED2 = 12;
	const MESSAGE = 7;
	const MOBILE_ACTIVATION_REQUIRED = 6;
	const VOTES_PAYMENT = 9;
	const ZERO_ZONE = 10;
	const ADVERTISEMENT = -1;
	
	public static $VALUES = array(
		self::OK,
		self::EMAIL_NOT_CONFIRMED,
		self::CAPTCHA,
		self::ERROR,
		self::RELOAD,
		self::AUTH_FAILED,
		self::REDIRECT,
		self::MOBILE_VALIDATION_REQUIRED,
		self::MOBILE_VALIDATION_REQUIRED2,
		self::MESSAGE,
		self::MOBILE_ACTIVATION_REQUIRED,
		self::VOTES_PAYMENT,
		self::ZERO_ZONE,
		self::ADVERTISEMENT
	);
}
?>