<?php

class PttCrawler
{
	private $db = null; // DB物件
	private $board_name = null; // 版名
	private $expired = false; // 文章是否過期
	private $is_last = false; // 是否為最後一頁

	public function __construct($db, $board_name)
	{
		date_default_timezone_set("Asia/Taipei");

		$this->db = $db;
		$this->board_name = $board_name;
	}

	// 主程式邏輯
	public function main()
	{
		$fe = fopen('php://stderr', 'w');
		// 取得總頁數
		$last_page = $this->page_count();

		for ($i = $last_page; $i >= 1; $i--) {
			// 檢查爬蟲是否該繼續爬資料
			if ($this->expired || $this->is_last) break;
			// 取得每頁文章基本資料
			$fetch_data = $this->fetch_item($i);
			// 存入每頁文章基本資料
			foreach ($fetch_data as $item) {
				$this->save_single_list($fetch_data, $item);
			}
			// 存入每筆文章詳細資料
			foreach ($fetch_data as $item) {
				$this->save_single_article($item);
			}
		}
		// 檢測文章是否過期
		if ($this->expired) {
			fwrite($fe, "articles are expired! stop fetching... \n");
		// 檢查是否已經抓到上次的最後一篇
		} else if ($this->is_last) {
			fwrite($fe, "no more lastest pages! stop fetching... \n");
		}
		fwrite($fe, "fetch finished! \n");
		fclose($fe);
	}

	// 取得該版總頁數
	private function page_count()
	{
		$fe = fopen('php://stderr', 'w');
		$result = array();
		$dom = str_get_html($this->fetch_page_html(null));
		foreach ($dom->find('a[class=btn wide]') as $element) {
			array_push($result, $element->href);
		}
		$last_page = str_replace(array("/bbs/" . $this->board_name . "/index", ".html"), "", $result[1]) + 1;
		fwrite($fe, "total page: " . $last_page . "\n");
		fclose($fe);
		return $last_page;
	}

	// 取得當頁的文章基本資料
	private function fetch_item($index)
	{
		$fe = fopen('php://stderr', 'w');
		fwrite($fe, "fetching page: " . $index . "\n");
		$dom = str_get_html($this->fetch_page_html($index));
		// 如果取得資料失敗, 回傳NULL
		if ($dom == NULL) {
			return NULL;
		}
		$result = array();
		$post_temp = array();
		$count = 0;
		foreach ($dom->find('div[class=title] a, div[class=date], div[class=author]') as $element) {
			$count++;
			$post = array();
			if ($count % 3 == 1) {
				$post_temp["url"] = str_replace(array("/bbs/" . $this->board_name . "/", ".html"), "", $element->href);
				$post_temp["title"] = $element->plaintext;
				// 過濾被刪除文章
				if (empty($post_temp["url"])) {
					$post_temp = array();
					$count = 0;
				}
			} else if ($count % 3 == 2) {
				$post_temp["date"] = $element->plaintext;
			} else {
				$post_temp["author"] = $element->plaintext;
				array_push($result, $post_temp);
				$post_temp = array();
			}
		}
		fclose($fe);
		return $result;
	}

	// 取得當頁的html
	private function fetch_page_html($index = null)
	{
		$fe = fopen('php://stderr', 'w');
		$result = null;
		$url = "https://www.ptt.cc/bbs/{$this->board_name}/index{$index}.html";
		$context = $this->init_opts();

		$error_count = 0;
		while ($error_count < 3 && ($result = @file_get_contents($url, false, $context)) == false) {
			fwrite($fe, "connection error, retry... \n");
			sleep(ERROR_SLEEP);
			$error_count++;
		}
		fclose($fe);
		return $result;
	}

	// 取得當篇文章的html
	private function fetch_article_html($id)
	{
		$fe = fopen('php://stderr', 'w');
		$result = null;
		$url = "https://www.ptt.cc/bbs/{$this->board_name}/{$id}.html";
		$context = $this->init_opts();

		// 連線逾時超過三次, 回傳NULL
		$error_count = 0;
		while ($error_count < 3 && ($result = @file_get_contents($url, false, $context)) == false) {
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
	private function fetch_article($id)
	{
		$dom = str_get_html($this->fetch_article_html($id));
		// 如果取得資料失敗, 回傳NULL
		if ($dom == NULL) {
			return $dom;
		}
		$result = array();
		$count = 0;
		foreach ($dom->find('span[class=article-meta-value]') as $element) {
			$count++;
			if ($count % 4 == 0) {
				$result["article_id"] = $id;
				$result["article_time"] = trim($element->plaintext);
			} elseif ($count % 4 == 1) {
				$result["article_author"] = trim($element->plaintext);
			}
		}
		// 取得內文
		foreach ($dom->find('div[id=main-container]') as $element) {
			$result["article_content"] = strip_tags(trim($element));
			$pos_1 = strpos($result["article_content"], @$result["article_time"]);
			$pos_2 = strpos($result["article_content"], "※ 發信站");
			$result["article_content"] = substr($result["article_content"], $pos_1 + strlen(@$result["article_time"]), $pos_2 - $pos_1 - 28);
		}

		// 過濾詭異文章
		if (!isset($result["article_id"])) {
			return NULL;
		}
		return $result;
	}

	// 存入單頁文章基本資料
	private function save_single_list($fetch_data, $item)
	{
		$fe = fopen('php://stderr', 'w');

		// 過濾失敗文章
		if ($fetch_data == NULL) {
			fwrite($fe, "notice! list: " . $i . " was skipped \n");
		// 略過已抓過的文章
		} else if ($this->db->IsArticle($item["url"])) {
			fwrite($fe, "notice! article: " . $item["url"] . " has been in database \n");
			if (IS_LAST) {
				$this->is_last = true;
			}
		// 略過已過期文章
		} else if ($this->is_date_over($item["date"])) {
			fwrite($fe, "notice! article: " . $item["url"] . " has been expired \n");
			$this->expired = true;
		}
		if (!$this->is_last && !$this->expired) {
			$this->db->InsertList($item, $this->board_name);
		}
		fclose($fe);
		sleep(LIST_SLEEP);
	}

	// 存入當篇文章的詳細資料
	private function save_single_article($item)
	{
		$count = 0;
		foreach ($item as $id) {
			$count++;
			if ($count % 4 == 1 && !$this->is_last && !$this->expired) {
				$fe = fopen('php://stderr', 'w');
				fwrite($fe, "fetching article id: " . $id . "\n");
				$insert_data = $this->fetch_article($id);
				// 過濾詭異文章
				if ($insert_data == NULL) {
					fwrite($fe, "notice! article: " . $id . " was skipped \n");
				} else {
					$this->db->InsertArticle($insert_data, $this->board_name);
				}
				fclose($fe);
				sleep(ARTICLE_SLEEP);
			}
		}
	}

	private function is_date_over($article_date)
	{
		return (strtotime(date($article_date)) <= strtotime(LAST_DATE)) ? TRUE : FALSE;
	}

	private function init_opts()
	{
		$opts = array(
			'http' => array(
				'method' => "GET",
				'timeout' => TIMEOUT,
				'header' => "Accept-language: zh-TW\r\n" . "Cookie: over18=1\r\n",
				'User-Agent' => "Mozilla/5.0 (Windows NT 6.3; WOW64; Trident/7.0; rv:11.0) like Gecko"
			)
		);
		return stream_context_create($opts);
	}
}