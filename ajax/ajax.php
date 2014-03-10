<?php
class AjaxResponse {
	public $success=false;
	public $data=null;
	public $errors=array();
}

header("Content-Type: application/json", true);
?>