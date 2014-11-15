<?php namespace Us\Crawler\Storage;
use \PDO;

class Database
{
	public $db = null;

	function __construct()
	{
		$this->openPDO();
	}

	private function openPDO()
	{
		// connection info for PDO
		define("DB_TYPE", "mysql");
		define("DB_HOST", "127.0.0.1");
		define("DB_NAME", "ptt_crawler");
		define("DB_USER", "root");
		define("DB_PASS", "");

		// error code of PDO
		define("SERVER_SHUTDOWN_CODE", "1053");

		$options = array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8', PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ, PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING);
		try {
			$this->db = new PDO(DB_TYPE . ':host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS, $options);
			$this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
			exit("Database connection error\n");
		}
	}

	public function reconnectPDO()
	{
		$this->db = null;
		$this->openPDO();
	}

	public function __destruct()
	{
		$this->db = null;
	}
}
