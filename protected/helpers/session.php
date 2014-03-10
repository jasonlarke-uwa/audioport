<?php
define('SESSION_TIMEOUT', 900); // 15-minute sessions
session_start();

if (!isset($_SESSION['expires']) || (time() < strtotime($_SESSION['expires']))) {
	$_SESSION['expires'] = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
}
else {
	// session expired since the last request, reset the variables
	foreach($_SESSION as $sv=>$v) {
		unset($_SESSION[$sv]);
	}
}

function isAuthenticated() {
	return !empty($_SESSION['username']);
}
?>