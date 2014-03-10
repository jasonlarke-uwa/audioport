<?php
require_once "VkBase.php";
require_once "VkSong.php";

class VkAudio extends VkBase {
	
	public function __construct($username, $password, $token = null) {
		parent::__construct($username, $password, $token);
		$this->_url = 'http://vk.com/audio';
	}
	
	public function search($query, $offset = 0) {
		$token = $this->token();
		$songs = null;
		
		$response = $this->action("search", array(
			'al' => '1',
			'id' => $token !== null ? $token->user_id() : '',
			'q' => $query,
			'offset' => $offset
		));

		if ($this->process($response)) {
			$answers = $response->answers();
			$songs = array();

			foreach($answers as $answer) {
				// special 'answers' are prefixed with a <!. These typically contain
				// metadata/javascript references and aren't relevant to what we want here.
				if (strpos($answer, '<!') === 0) {
					continue;
				}
				
				$marker = '<div class="audio ';
				$marklen = strlen($marker);
				$pos = strpos($answer, $marker, 0);
				
				while($pos !== false) {
					$pos += $marklen;
					$finish = strpos($answer, $marker, $pos);
					
					$html = $finish 
						? substr($answer, $pos, $finish - $pos) 
						: substr($answer, $pos);
						
					$song = VkSong::from_html($html);
					if ($song !== null) {
						$songs[] = $song;
					}
					
					$pos = $finish;	
				}
			}
		}
		return $songs;
	}
}
?>