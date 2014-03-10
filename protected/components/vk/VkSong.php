<?php
class VkSong {
	private $_id;
	private $_oid;
	private $_url;
	private $_artist;
	private $_title;
	private $_length;
	
	// getters.
	public function id() 		{ return $this->_id; }
	public function owner()		{ return $this->_oid; }
	public function url() 		{ return $this->_url; }
	public function artist()	{ return $this->_artist; }
	public function title()		{ return $this->_title; }
	public function length()	{ return $this->_length; }
	
	public function __construct($id, $oid, $url, $artist, $title, $length) {
		$this->_id = $id;
		$this->_oid = $oid;
		$this->_url = $url;
		$this->_artist = $artist;
		$this->_title = $title;
		$this->_length = $length;
	}

	// Dirty, dirty parsing of the HTML. If anything is going to break down the line, this is
	// most likely it. Unfortunately VK sends an absolute boatload of HTML back for ajax requests instead
	// of a normal JSON string or some other portable format. If they ever change their HTML layout for the 
	// results this will need to be rewritten.
	public static function from_html($html) {
		$infoNode = self::string_between($html, 'class="info', '<div id="lyrics');
		$playNode = self::string_between($html, 'class="play_btn', 'class="info');
		
		if ($infoNode == false || $playNode === false) {
			return null;
		}
		
		$aid = '';
		$id = '0';
		$oid = '';
		$url = '';
		$len = 0;
	
		$artist = self::decode_entity(strip_tags(self::string_between($infoNode, '<b>', '</b>')));
		$title = self::decode_entity(strip_tags(self::string_between($infoNode, 'class="title">', '<span class="user"')));
		
		$aid = self::string_between($html, 'id="audio', '"');
		if (strpos($aid, '_') !== false) {
			$parts = explode('_', $aid);
			$id = intval($parts[1]) !== 0 ? $parts[1] : '0';
			$oid = $parts[0];
		}
		
		$hiddenField = self::string_between($playNode, sprintf('id="audio_info%s"', $aid), '/>');
		$hiddenValues = explode(',', self::string_between($hiddenField, 'value="', '"'));
		
		if (count($hiddenValues) === 2) {
			$len = intval($hiddenValues[1]);
			$url = $hiddenValues[0];
		}
		
		return $id !== '0' && !empty($url)
			? new VkSong($id, $oid, $url, $artist, $title, $len)
			: null;
	}
	
	private static function decode_entity($ent) {
		$ent = html_entity_decode($ent, ENT_QUOTES);
		$ent = preg_replace('/&#(\d{2,4});/e',"chr(\\1)", $ent); 
		$ent = preg_replace('/&#x([a-f0-9]{2,4});/ei',"chr(0x\\1)", $ent);
		return $ent;		
	}
	
	private static function string_between($subject, $p1, $p2, $start = 0) {
		$i1 = strpos($subject, $p1, $start);
		if ($i1 === false) { return false; }
		$i1 += strlen($p1);
		$i2 = strpos($subject, $p2, $i1);
		if ($i2 === false) { return false; }
		
		return substr($subject, $i1, $i2 - $i1);
	}
}
?>