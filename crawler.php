<?php
require 'Parser.php';
require 'RDB.php';

$board_name = null;
if (isset($argv[1])) {
	$board_name = $argv[1];
} else {
	exit("usage: php crawler.php {Borard Name}");
}

$db = new RDB();

fetch_board($db, $board_name);
//save_article($db, $board_name);
echo "fetch finished! \n";



// 取得該board所有文章基本資料
function fetch_board($db, $board_name)
{
	$arr_post_list = array();
	$last_page = page_count($board_name, null) + 1;
	echo "fetching board... \n";
	echo "total page: " . $last_page . "\n";
	for ($i= $last_page; $i >= 1 ; $i--) {
		echo "fetching page: " . $i ."\n";
		$fetch_data = fetch_item($board_name, $i);
		foreach($fetch_data as $item) {
			// 過濾失敗文章
			if ($fetch_data == NULL) {
				echo "notice! list: " . $i ." was skipped \n";
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
		//sleep(0.5);
	}
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
	$result = null;
	$url = "https://www.ptt.cc/bbs/{$board_name}/index{$index}.html";
	$opts = array(
		'http'=>array(
			'method' => "GET",
			'timeout'=> 10,
			'header' => "Accept-language: zh-TW\r\n",
			'User-Agent' => "Mozilla/5.0 (Windows NT 6.3; WOW64; Trident/7.0; rv:11.0) like Gecko" .
			"Cookie: over18=1\r\n"
			)
		);
	$context = stream_context_create($opts);

	$error_count = 0;
	while ($error_count < 3 && ($result = @file_get_contents($url, false, $context)) == false)
	{
		echo "connection error, retry... \n";
		sleep(2);
		$error_count++;
	}

	return $result;
}

/*
 * 此區段以下開始抓每篇文章詳細資料
*/

// 取得當篇文章的html
function fetch_article_html($board_name, $id)
{
	$result = null;
	$url = "https://www.ptt.cc/bbs/{$board_name}/{$id}.html";
	$opts = array(
		'http'=>array(
			'method' => "GET",
			'timeout'=> 10,
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
			echo "response 404..., this article will be skipped \n";
			$error_count = 4;
		} else {
			echo "connection error, retry... \n";
			sleep(2);
			$error_count++;
		}
	}

	return $result;
}

// 取得當篇文章的詳細資料
function fetch_article($board_name, $id)
{
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
		$pos_2 = strpos($result["article_content"], "-- ※ 發信站");
		$result["article_content"] = substr($result["article_content"], $pos_1 + strlen(@$result["article_time"]), $pos_2 - $pos_1 - 24);
	}

	// 過濾詭異文章
	if (!isset($result["article_id"])) {
		return NULL;
	}

	return $result;
}
/*
// 存入每篇文章的詳細資料
function save_article($db, $board_name)
{
	$list = $db->GetList($board_name);
	foreach ($list as $post) {
		echo "fetching article id: " . $post->post_id . "\n";
		$insert_data = fetch_article($board_name, $post->post_id);
		// 過濾詭異文章
		if ($insert_data == NULL) {
			echo "notice! article: " . $post->post_id ." was skipped \n";
			continue;
		}
		$db->InsertArticle($insert_data, $board_name);
		//sleep(0.5);
	}
}*/

// 存入當篇文章的詳細資料
function save_single_article($db, $board_name, $id)
{
	echo "fetching article id: " . $id . "\n";
	$insert_data = fetch_article($board_name, $id);
		// 過濾詭異文章
	if ($insert_data == NULL) {
		echo "notice! article: " . $id ." was skipped \n";
	} else {
		$db->InsertArticle($insert_data, $board_name);
	}
	//sleep(0.5);
}
?>