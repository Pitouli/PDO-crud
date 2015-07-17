<?php
	
	require_once 'config.php'; // Database setting constants [DB_HOST, DB_NAME, DB_USERNAME, DB_PASSWORD]
	require '../src/PDOcrud.class.php';
	
	class DB extends PDOcrud {
	
		function __construct() {
			$dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8';
			try {
				parent::__construct($dsn, DB_USERNAME, DB_PASSWORD);
			} 
			catch (PDOException $e) {
				$response["status"] = "error";
				$response["message"] = 'Connection failed: ' . $e->getMessage();
				$response["data"] = null;
				exit;
			}
		}
		
	}