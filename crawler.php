<?php
require 'bootstrap.php';

$board_name = null;
$shortopts  = "";
$longopts  = array(
	"board:",
	"sleep-between-list:",
	"sleep-between-article:",
	"sleep-between-retry:",
	"timeout:",
	"stop-date:",
	"stop-on-duplicate",
	"debug",
	"help"
);

$options = getopt($shortopts, $longopts);


if (isset($options['help']) || !array_key_exists('board', $options)) {
	$fe = fopen('php://stderr', 'w');
	$help_msg = <<<EOF
Usage: php crawler.php --board=<board name> {options}
  --sleep-between-list=INT    : Seconds to sleep between fetching different index pages.
  --sleep-between-article=INT : Seconds to sleep between fetching articles.
  --sleep-between-retry=INT   : Seconds to sleep when error occurrs.
  --timeout=INT               : Seconds of the http timeout.
  --stop-date=DATE            : Stop the program when articles older than the specific date. (default: today)
  --stop-on-duplicate         : Stop crawling when articles are duplicated. (default: true)
  --debug                     : Enable debug.
EOF;

	fwrite($fe, "$help_msg\n");
	exit(1);
}

$board_name = $options['board'];

$db = new RDB();
$PttCrawler = new PttCrawler($db, $board_name);
$PttCrawler->set_config(
	array(
		"list_sleep" => isset($options['sleep-between-list']) ? $options['sleep-between-list'] : null,
		"article_sleep" => isset($options['sleep-between-article']) ? $options['sleep-between-article'] : null,
		"error_sleep" => isset($options['sleep-between-retry']) ? $options['sleep-between-retry'] : null,
		"timeout" => isset($options['timeout']) ? $options['timeout'] : null,
		"stop-date" => isset($options['stop-date']) ? $options['stop-date'] : null,
		"stop-on-duplicate" => isset($options['stop-on-duplicate']) ? true : null,
	)
);
$PttCrawler->run();