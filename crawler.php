<?php
require 'Parser.php';
require 'RDB.php';
require 'PttCrawler.php';

CONST LIST_SLEEP = 0; // 每頁抓完間隔時間
CONST ARTICLE_SLEEP = 0; // 每篇抓完間隔時間
CONST ERROR_SLEEP = 2; // 連線失敗間隔時間
CONST TIMEOUT = 10; // 連線送出timeout
CONST LAST_DATE = "2014-11-05"; // 抓取文章的最舊日期
CONST IS_LAST = TRUE; // 設定是否只抓到上次的最後一篇

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
$PttCrawler->main();
