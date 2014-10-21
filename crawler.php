<?php
require 'Parser.php';
require 'RDB.php';

CONST LIST_SLEEP = 0; // 每頁抓完間隔時間
CONST ARTICLE_SLEEP = 0; // 每篇抓完間隔時間
CONST ERROR_SLEEP = 2; // 連線失敗間隔時間
CONST TIMEOUT = 10; // 連線送出timeout
CONST LAST_DATE = "2014-10-01"; // 抓取文章的最舊日期

$board_name = null;
if (isset($argv[1])) {
	$board_name = $argv[1];
} else {
	$fe = fopen('php://stderr', 'w');
	fwrite($fe, "usage: php crawler.php {Board Name} \n");
	fclose($fe);
	exit();
}

$db = new RDB();

$fe = fopen('php://stderr', 'w');
fetch_board($db, $board_name);
fwrite($fe, "fetch finished! \n");
fclose($fe);

// 取得該board所有文章基本資料
function fetch_board($db, $board_name)
{
	$fe = fopen('php://stderr', 'w');
	$arr_post_list = array();
	$last_page = page_count($board_name, null) + 1;
	fwrite($fe, "total page: " . $last_page . "\n");
	$expired = false;
	for ($i= $last_page; $i >= 1 ; $i--) {
		fwrite($fe, "fetching page: " . $i ."\n");
		$fetch_data = fetch_item($board_name, $i);
		// 檢測文章是否過期
		if ($expired) {
			fwrite($fe, "articles are expired! stop fetching... \n");
			break;
		}
		foreach($fetch_data as $item) {
			// 過濾失敗文章
			if ($fetch_data == NULL) {
				fwrite($fe, "notice! list: " . $i ." was skipped \n");
				continue;
			// 略過已抓過的文章
			} else if ($db->IsArticle($item["url"])) {
				fwrite($fe, "notice! article: " . $item["url"] ." has been in database \n");
				continue;
			// 略過已過期文章
			} else if (is_date_over($item["date"])) {
				fwrite($fe, "notice! article: " . $item["url"] ." has been expired \n");
				$expired = true;
				continue;
			}
			$db->InsertList($item, $board_name);
			// 存入該頁所有文章詳細資料
			$count = 0;
			foreach($item as $article_arr) {
				$count++;
				if ($count % 4 == 1) {
					save_single_article($db, $board_name, $article_arr);
				}
			}
		}
		sleep(LIST_SLEEP);
	}
	fclose($fe);
}

// 取得該版總頁數
function page_count($board_name)
{
	$result = array();
	$dom = str_get_html(fetch_page_html($board_name));
	foreach($dom->find('a[class=btn wide]') as $element) {
		array_push($result, $element->href);
	}
	return str_replace(array("/bbs/" . $board_name . "/index",".html"), "", $result[1]);;
}

// 取得當頁的文章基本資料
function fetch_item($board_name, $index)
{
	$dom = str_get_html(fetch_page_html($board_name, $index));
	// 如果取得資料失敗, 回傳NULL
	if ($dom == NULL) {
		return NULL;
	}
	$result = array();
	$post_temp = array();
	$count = 0;
	foreach($dom->find('div[class=title] a, div[class=date], div[class=author]') as $element) {
		$count++;
		$post = array();
		if ($count % 3 == 1) {
			$post_temp["url"] = str_replace(array("/bbs/" . $board_name . "/", ".html"), "", $element->href);
			$post_temp["title"] = $element->plaintext;
		} else if ($count % 3 == 2) {
			$post_temp["date"] = $element->plaintext;
		} else {
			$post_temp["author"] = $element->plaintext;
			array_push($result, $post_temp);
			$post_temp = array();
		}
	}

	return $result;
}

// 取得當頁的html
function fetch_page_html($board_name, $index = null)
{
	$fe = fopen('php://stderr', 'w');
	$result = null;
	$url = "https://www.ptt.cc/bbs/{$board_name}/index{$index}.html";
	$opts = array(
		'http'=>array(
			'method' => "GET",
			'timeout'=> TIMEOUT,
			'header' => "Accept-language: zh-TW\r\n",
			'User-Agent' => "Mozilla/5.0 (Windows NT 6.3; WOW64; Trident/7.0; rv:11.0) like Gecko" .
			"Cookie: over18=1\r\n"
			)
		);
	$context = stream_context_create($opts);

	$error_count = 0;
	while ($error_count < 3 && ($result = @file_get_contents($url, false, $context)) == false)
	{
		fwrite($fe, "connection error, retry... \n");
		sleep(ERROR_SLEEP);
		$error_count++;
	}
	fclose($fe);
	return $result;
}

// 取得當篇文章的html
function fetch_article_html($board_name, $id)
{
	$fe = fopen('php://stderr', 'w');
	$result = null;
	$url = "https://www.ptt.cc/bbs/{$board_name}/{$id}.html";
	$opts = array(
		'http'=>array(
			'method' => "GET",
			'timeout'=> TIMEOUT,
			'header' => "Accept-language: zh-TW\r\n",
			'User-Agent' => "Mozilla/5.0 (Windows NT 6.3; WOW64; Trident/7.0; rv:11.0) like Gecko" .
			"Cookie: over18=1\r\n"
			)
		);
	$context = stream_context_create($opts);

	// 連線逾時超過三次, 回傳NULL
	$error_count = 0;
	while ($error_count < 3 && ($result = @file_get_contents($url, false, $context)) == false)
	{
		$headers = get_headers($url);
		$response = substr($headers[0], 9, 3);
		if ($response == "404") {
			fwrite($fe, "response 404..., this article will be skipped \n");
			$error_count = 4;
		} else {
			fwrite($fe, "connection error, retry... \n");
			sleep(ERROR_SLEEP);
			$error_count++;
		}
	}
	fclose($fe);
	return $result;
}

// 取得當篇文章的詳細資料
function fetch_article($board_name, $id)
{
	$fe = fopen('php://stderr', 'w');
	$dom = str_get_html(fetch_article_html($board_name, $id));
	// 如果取得資料失敗, 回傳NULL
	if ($dom == NULL) {
		return $dom;
	}
	$result = array();
	$count = 0;
	foreach($dom->find('span[class=article-meta-value]') as $element) {
		$count++;
		if ($count % 4 == 0) {
			$result["article_id"] = $id;
			$result["article_time"] = trim($element->plaintext);
		} elseif ($count % 4 == 1) {
			$result["article_author"] = trim($element->plaintext);
		}
	}
	// 取得內文
	foreach($dom->find('div[id=main-container]') as $element) {
		$result["article_content"] = strip_tags(trim($element));
		$pos_1 = strpos($result["article_content"], @$result["article_time"]);
		$pos_2 = strpos($result["article_content"], "※ 發信站");
		$result["article_content"] = substr($result["article_content"], $pos_1 + strlen(@$result["article_time"]), $pos_2 - $pos_1 - 28);
	}

	// 過濾詭異文章
	if (!isset($result["article_id"])) {
		return NULL;
	}
	fclose($fe);
	return $result;
}

// 存入當篇文章的詳細資料
function save_single_article($db, $board_name, $id)
{
	$fe = fopen('php://stderr', 'w');
	fwrite($fe, "fetching article id: " . $id . "\n");
	$insert_data = fetch_article($board_name, $id);
	// 過濾詭異文章
	if ($insert_data == NULL) {
		fwrite($fe, "notice! article: " . $id ." was skipped \n");
	} else {
		$db->InsertArticle($insert_data, $board_name);
	}
	fclose($fe);
	sleep(ARTICLE_SLEEP);
}

function is_date_over($article_date)
{
	date_default_timezone_set("Asia/Taipei");
	//date('D M d H:i:s Y')

	return (strtotime(date($article_date)) <= strtotime(LAST_DATE)) ? TRUE : FALSE;
}
?>