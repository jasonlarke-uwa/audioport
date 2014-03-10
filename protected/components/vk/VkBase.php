<?php
require_once "VkToken.php";
require_once "VkUtils.php";
require_once "VkResponse.php";
require_once "VkCaptcha.php";

class VkBase {
	const USER_AGENT = "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:20.0) Gecko/20100101 Firefox/20.0";
	
	protected $_url = 'http://login.vk.com';
	protected $_error = null;
	protected $_captcha = null;
	protected $_userId = '';
	
	private $_username = '';
	private $_password = '';
	private $_token = null;
	
	public function __construct($username, $password, $token = null) {
		$this->_username = $username;
		$this->_password = $password;
		$this->_token = $token;
	}
	
	// Gets the last Exception the class encountered. If $msg !== null then the last
	// error will be set to $msg (a new Exception will be created if $msg is a string)
	public function error($msg = null) {
		if ($msg !== null) {
			$this->_error = is_string($msg) ? new Exception($msg) : $msg;
		}
		return $this->_error;
	}
	
	// Get the captcha. If $sid is not null, a new VkCaptcha will be set with the
	// given $sid value.
	public function captcha($sid = null) {
		if ($sid !== null) {
			if ($this->_captcha === null || $this->_captcha->expired()) {
				$this->_captcha = new VkCaptcha($sid);
			}
		}
		elseif ($this->_captcha !== null && $this->_captcha->expired()) {
			$this->_captcha = null;
		}
		
		return $this->_captcha;
	}
	
	public function token() {
		if ($this->_token !== null && !$this->_token->expired()) {
			return $this->_token;
		}

		$query = http_build_query(array(
			"_origin" => "http://vk.com",
			"act" => "login", 
			"captcha_key" => "",
			"captcha_sid" => "",
			"email" => $this->_username,
			"expire" => "",
			"pass" => $this->_password,
			"role" => "al_frame",
			"to" => ""
		));
		
		// Simulate a login HTTP request to the VK servers.
		$request = curl_init('http://login.vk.com?act=login');
		curl_setopt($request, CURLOPT_POST, true);
		curl_setopt($request, CURLOPT_POSTFIELDS, $query);
		curl_setopt($request, CURLOPT_USERAGENT, self::USER_AGENT);
		$response = VkUtils::curl_exec_follow($request, 5);
		
		curl_close($request);

		if (empty($response)) {
			$this->error("Error sending request to the login server.");
			return null; 
		}

		$rawCookies = VkUtils::extract_cookies($response['headers']);
		$session = VkUtils::extract_session($rawCookies);

		$cookies = array();
		foreach($rawCookies as $cookie) {
			foreach($cookie['cookies'] as $name=>$value) {
				$cookies[$name] = $value;
			}
		}

		// Test the values of the cookies. Typically VK will set the session id to 'deleted' in the first request.
		// if the login in successful the following requests will overwrite the cookie to the real session id.
		if (empty($cookies['l'])) {
			$this->error("Invalid credentials supplied to login server.");
			return null;
		}
		else if (empty($session)) {
			$this->error("Error occurred creating session.");
			return null;
		}
		
		return ($this->_token = new VkToken(
			$session['id'], 
			$session['key'], 
			$cookies['l'],
			$session['expires']
		));
	}
	
	// Send an action to the server.
	protected function action($name, $params) {
		$params['act'] = $name;
		$data = http_build_query($params);
		
		$token = $this->token();
		if ($token === null) {
			return false;
		}
		$session = urlencode($token->session_key()) . '=' . urlencode($token->session_id());

		$request = curl_init($this->_url);
		curl_setopt($request, CURLOPT_POST, true);
		curl_setopt($request, CURLOPT_POSTFIELDS, $data);
		curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($request, CURLOPT_HTTPHEADER, array('X-Requested-With: XMLHttpRequest'));
		curl_setopt($request, CURLOPT_COOKIE, $session);
		curl_setopt($request, CURLOPT_USERAGENT, self::USER_AGENT);
		
		$response = curl_exec($request);
		curl_close($request);

		return VkResponse::parse($response);
	}

	// Process a VkResponse object. Sets captcha/errors if necessary.
	// returns true if the request is OK to use, or false if an invalid response code
	// is found.
	protected function process($response) {
		if ($response === false) {
			return false;
		}
		
		$valid = false;
		switch($response->code()) {
			case VkResponseType::OK:
			case VkResponseType::UNKNOWN:
				$valid = true; break;
			case VkResponseType::ERROR:
				$this->error(implode(' #', (array)$response->answers())); break;
			case VkResponseType::CAPTCHA:
				$answers = $response->answers();
				$this->captcha($answers[count($answers) - 2]);
				break;
			case VkResponseType::REDIRECT:
				$token = $this->token();
				if ($token === null) {
					return false;
				}
				
				$params = array(
					"act"=>"security_check",
					"al"=>"1",
					"al_page"=>"3",
					"code"=>"4247436",
					"hash"=>"8871a2e4ac5fbaddcc",
					"to"=>""
				);
				
				$session = urlencode($token->session_key()) . '=' . urlencode($token->session_id());
				$request = curl_init("https://vk.com/login.php");
				
				curl_setopt($request, CURLOPT_POST, true);
				curl_setopt($request, CURLOPT_POSTFIELDS, http_build_query($params));
				curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($request, CURLOPT_COOKIE, $session);
				
				curl_setopt($request, CURLOPT_USERAGENT, self::USER_AGENT);
				$r = VkUtils::curl_exec_follow($request, 5);
				curl_close($request);
				var_dump($r);exit;

				break;
			default:
				break;
		}
		return $valid;
	}
}
?>