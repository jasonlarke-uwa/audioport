<?php
class VkCaptcha {
	// rough estimate (in minutes) of the captcha expiration timer.
	const CAPTCHA_TIMEOUT = 5;

	private $_created;
	private $_id;
	
	public function __construct($sid) {
		$this->_created = time();
		$this->_id = $sid;
	}
	
	// Test if the Captcha has expired (again, estimated timeout value only)
	public function expired() {
		return (time() - $this->_created) >= (self::CAPTCHA_TIMEOUT * 60);
	}
	
	// Retrieve the URL for the captcha image.
	public function url() {
		$query = http_build_query(array('sid' => $this->_id, 's' => '1'));
		return "http://vk.com/captcha.php?{$query}";
	}
}
?>