<?php
require 'Parser.php';
require 'RDB.php';
require 'PttCrawler.php';

$board_name = null;
if (isset($argv[1])) {
	$board_name = $argv[1];
} else {
	$fe = fopen('php://stderr', 'w');
	fwrite($fe, "usage: php crawler.php {Board Name} --last=true\n");
	fclose($fe);
	exit();
}

$db = new RDB();
$PttCrawler = new PttCrawler($db, $board_name);
$PttCrawler->set_config(array(
	"last_date" => "2014-11-08"));
$PttCrawler->main();