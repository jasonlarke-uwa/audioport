<?php
require_once __DIR__ . "/../protected/components/vk/VkAudio.php";
require_once __DIR__ . "/../protected/helpers/database.php";
require_once __DIR__ . "/../protected/helpers/session.php";
require_once __DIR__ . "/../protected/helpers/auth.php";
require_once "ajax.php";

class DataWrapper {
	public $songs=null;
	public $captcha=null;
}

class SongWrapper {
	public $title;
	public $artist;
	public $url;
	public $length;
	public $id;
	public $genre;
	public $album;
	public $year;
	public $track;
	public $cached;
	
	public function __construct($t,$a,$u,$l,$id,$g,$alb,$y,$trk,$c) {
		$this->title = $t;
		$this->artist = $a;
		$this->url = $u;
		$this->length = $l;
		$this->id = $id;
		$this->genre = $g;
		$this->album = $alb;
		$this->year = $y;
		$this->track = $trk;
		$this->cached = $c;
	}
	
	public static function toTimeString($seconds) {
		$h = $seconds / 3600;
		$m = $seconds / 60 % 60;
		$s = $seconds % 60;
		
		return $h >= 1
			? sprintf('%02d:%02d:%02d', $h, $m, $s)
			: sprintf('%02d:%02d', $m, $s);
	}
}

function getSongId($s) { 
	return $s->id();
}

function getSongsInformation($songs) {
	global $database;
	$ids = array_map("getSongId", $songs);
	$query = "
		SELECT 
			vk_id,
			genre,
			album,
			year,
			track
		FROM vk_song_info 
		WHERE vk_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
	";
	
	$results = $database->queryAll($query, $ids);
	$infos = array();
	
	foreach($results as $result) {
		$id = $result['vk_id'];
		$infos[$id] = array();
		
		$infos[$id]['genre'] = $result['genre'];
		$infos[$id]['album'] = $result['album'];
		$infos[$id]['year'] = $result['year'];
		$infos[$id]['track'] = $result['track'];
	}
	
	return $infos;
}

$response = new AjaxResponse();
if (!empty($_GET["query"])) {
	$wrapper = new DataWrapper();

	$query = $_GET["query"];
	$token = empty($_SESSION["token"]) ? null : VkToken::deserialize($_SESSION["token"]);
	$offset = empty($_GET['offset']) ? 0 : intval($_GET['offset']);
	
	$audio = new VkAudio(VK_USERNAME, VK_PASSWORD, $token);
	$songs = $audio->search($query, $offset);
	
	if ($songs === null) {
		if ($audio->captcha() !== null) 
			$wrapper->captcha = $audio->captcha()->url();
		elseif ($audio->token() === null)
			$response->errors[] = "Unable to retrieve a valid token: {$audio->error()->getMessage()}";
		else
			$response->errors[] = "An unknown error occurred. Please try again";
	}
	else {
		$wrapper->songs = array();
		if (count($songs) > 0) {
			$infos = getSongsInformation($songs);
			
			foreach($songs as $vks) {
				$cached = isset($infos[$vks->id()]);
				
				$wrapper->songs[] = new SongWrapper(
					$vks->title(),
					$vks->artist(),
					$vks->url(),
					SongWrapper::toTimeString($vks->length()),
					$vks->id(),
					$cached ? $infos[$vks->id()]['genre'] : null,
					$cached ? $infos[$vks->id()]['album'] : null,
					$cached ? $infos[$vks->id()]['year'] : null,
					$cached ? $infos[$vks->id()]['track'] : null,
					$cached
				);
			}
		}

		$_SESSION["token"] = $audio->token()->serialize();
		$response->data = $wrapper;
		$response->success = true;
	}
}
else {
	$response->errors[] = "Missing required paramater: query";
}

echo json_encode($response);
?>