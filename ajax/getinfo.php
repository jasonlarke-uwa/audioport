<?php
require_once "../protected/helpers/database.php";
require_once "../protected/components/misc/ID3v2.php";
require_once "ajax.php";

function parse($iterator, $type) {
	switch($type) {
	case 'text': return $iterator->text();
	default: return $iterator->data();
	}
}

function lookupInfo($id) {
	global $database;
	$result = $database->queryFirst("
		SELECT vk_id, title, artist, genre, album, year, track
		FROM vk_song_info
		WHERE vk_id = ?
	", array(intval($id)));
	
	if ($result) {
		return array(
			'id' => $result['vk_id'],
			'genre' => $result['genre'],
			'album' => $result['album'],
			'year' => $result['year'],
			'track' => $result['track']
		);
	}
	return null;
}

$tags = array(
	"TIT2" => array("name" => "title", "type" => "text"),
	"TT2" => array("name" => "title", "type" => "text"),

	"TALB" => array("name" => "album", "type" => "text"),
	"TAL" => array("name" => "album", "type" => "text"),

	"TPE1" => array("name" => "artist", "type" => "text"),
	"TP1" => array("name" => "artist", "type" => "text"),

	"TYER" => array("name" => "year", "type" => "text"),
	"TYE" => array("name" => "year", "type" => "text"),

	"TCON" => array("name" => "genre", "type" => "text"),
	"TCO" => array("name" => "genre", "type" => "text"),

	"TRK" => array("name" => "track", "type" => "text"),
	"TRCK" => array("name" => "track", "type" => "text")
);

$neededTags = array('genre','album','year','track');

$response = new AjaxResponse();
if (!empty($_GET['id']) && !empty($_GET['url'])) {
	if (($info = lookupInfo($_GET['id'])) === null) {
		$response->data = array('id' => $_GET['id'], 'title' => trim($_GET['title']), 'artist' => trim($_GET['artist']));
		foreach($neededTags as $t)
			$response->data[$t] = null;
		
		$it = ID3v2::get_iterator($_GET['url']);
		if ($it !== NULL) {
			foreach($it as $k=>$frame) {
				if (isset($tags[$k]) && in_array($tags[$k]['name'], $neededTags)) {
					$response->data[$tags[$k]['name']] = parse($it, $tags[$k]['type']);
				}
			}
			
			$database->execute("
				INSERT INTO vk_song_info (vk_id,title,artist,genre,album,year,track,date_created)
				VALUES (?,?,?,?,?,?,?,NOW())
			", array(
				intval($_GET['id']), 
				trim($_GET['title']),
				trim($_GET['artist']),
				$response->data['genre'],
				$response->data['album'],
				$response->data['year'],
				$response->data['track']
			));
			
			$it->dispose();
			$response->success = true;
		}
		else {
			$response->errors[] = 'Provided resource does not contain a valid ID3v2 tag';
			$database->execute("
				INSERT INTO vk_song_info (vk_id,title,artist,genre,album,year,track,date_created)
				VALUES (?,?,?,?,?,?,?,NOW())
			", array(
				intval($_GET['id']), 
				trim($_GET['title']),
				trim($_GET['artist']),
				null,
				null,
				null,
				null
			));
		}
	}
	else {
		$response->data = $info;
		$response->success = true;
	}
}
else {
	$response->errors[] = "Missing required parameter: " . (empty($_GET['id']) ? 'id' : 'url');
}

echo json_encode($response);
?>