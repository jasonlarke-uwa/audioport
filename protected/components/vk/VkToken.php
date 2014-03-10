<?php
class VkToken {
	private $_expires;
	private $_sid;
	private $_uid;
	private $_skey;
	
	public function __construct($sessionId, $sessionKey, $userId, $expires) {
		$this->_uid = $userId;
		$this->_skey = $sessionKey;
		$this->_sid = $sessionId;
		$this->_expires = $expires;
	}
	
	public function expired() {
		return $this->_expires <= time();
	}
	
	public function session_id() {
		return $this->_sid;
	}
	
	public function session_key() {
		return $this->_skey;
	}
	
	public function user_id() {
		return $this->_uid;
	}
	
	// Decode a token serialized with the VkToken::serialize() method
	public static function deserialize($base64) {
		parse_str(base64_decode($base64), $params);
		if (empty($params['sid']) || empty($params['skey']) || empty($params['uid']) || empty($params['e'])) {
			return null;
		}
		return new VkToken($params['sid'], $params['skey'], $params['uid'], intval($params['e']));
	}
	
	// Serialize the token into a portable string, useful for sending tokens over the network (client/server..etc)
	public function serialize() {
		// really basic serialization. Format the fields as a http query, then base64 encode it.
		return base64_encode(http_build_query(array(
			'sid' => $this->_sid,
			'skey' => $this->_skey,
			'uid' => $this->_uid,
			'e' => $this->_expires
		)));
	}
}
?>