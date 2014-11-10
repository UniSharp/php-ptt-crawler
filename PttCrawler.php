<?php

class PttCrawler
{
	private $db = null; // DB物件
	private $board_name = null; // 版名
	private $config = array(); // 設定參數陣列

	public function __construct($db, $board_name)
	{
		date_default_timezone_set("Asia/Taipei");

		$this->db = $db;
		$this->board_name = $board_name;
		$this->set_config(null);
	}

	public function set_config($config)
	{
		// 每頁清單抓完間隔時間
		$this->config["list_sleep"] = (!isset($config["list_sleep"])) ? 0 : $config["list_sleep"];
		// 每篇文章抓完間隔時間
		$this->config["article_sleep"] = (!isset($config["article_sleep"])) ? 0 : $config["article_sleep"];
		// 連線失敗間隔時間
		$this->config["error_sleep"] = (!isset($config["error_sleep"])) ? 2 : $config["error_sleep"];
		// 連線送出timeout
		$this->config["timeout"] = (!isset($config["timeout"])) ? 10 : $config["timeout"];
		// 抓取文章的最後日期
		$this->config["last_date"] = (!isset($config["last_date"])) ? date("Y-m-d") : $config["last_date"];
		// 設定是否只抓到上次的最後一篇
		$this->config["is_last_date"] = (!isset($config["is_last_date"])) ? TRUE : $config["is_last_date"];
	}

	// 主程式邏輯
	public function main()
	{
		$is_to_date = false;
		$is_last = false;
		// 取得總頁數
		$last_page = $this->page_count();

		for ($i = $last_page; $i >= 1; $i--) {
			// 檢查爬蟲是否該繼續爬資料
			if ($is_to_date || $is_last) break;
			// 取得每頁文章基本資料
			$current_page = $this->fetch_page($i);

			// 過濾失敗文章
			if ($current_page == NULL) {
				$this->error_output("notice! list: " . $i . " was skipped \n");
				continue;
			}

			$save_article_arr = array();
			foreach ($current_page as $item) {
				// 略過已抓過的文章
				if ($this->db->GetArticleByUrl($item["url"])) {
					$this->error_output("notice! article: " . $item["url"] . " has been in database \n");
					// 檢查是否抓到上次最後一篇
					if ($this->config["is_last_date"]) {
						$is_last = true;
					}
					continue;
				}
				// 略過已到期文章
				if ($this->is_date_over($item["date"])) {
					$this->error_output("notice! article: " . $item["url"] . " is earlier than " . $this->config["last_date"] . " \n");
					$is_to_date = true;
					continue;
				}
				// 存入要抓取詳細資料的article陣列
				array_push($save_article_arr, $item);
				// 存入每頁文章基本資料
				$this->save_single_list($item);
			}

			foreach ($save_article_arr as $item) {
				$this->error_output("fetching article id: " . $item["url"] . "\n");
				// 取得每筆文章詳細資料
				$article = $this->fetch_article($item["url"]);
				// 過濾詭異文章
				if ($article == NULL) {
					$this->error_output("notice! article: " . $item["url"] . " was skipped \n");
					continue;
				}
				// 存入每筆文章詳細資料(returned id)
				$this->save_single_article($article);
				// 清空article陣列
				$save_article_arr = array();
			}
		}
		// 檢測文章是否到期
		if ($is_to_date) {
			$this->error_output("articles are earlier than " . $this->config["last_date"] . ", stop fetching... \n");
		// 檢查是否已經抓到上次的最後一篇
		} else if ($is_last) {
			$this->error_output("no more lastest pages! stop fetching... \n");
		}
		$this->error_output("fetch finished! \n");
	}

	// 取得該版總頁數
	private function page_count()
	{
		$result = array();
		$dom = str_get_html($this->fetch_page_html(null));
		foreach ($dom->find('a[class=btn wide]') as $element) {
			array_push($result, $element->href);
		}
		$last_page = str_replace(array("/bbs/" . $this->board_name . "/index", ".html"), "", $result[1]) + 1;
		$this->error_output("total page: " . $last_page . "\n");
		return $last_page;
	}

	// 取得當頁的文章基本資料
	private function fetch_page($index)
	{
		$this->error_output("fetching page: " . $index . "\n");
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
		return $result;
	}

	// 取得當頁的html
	private function fetch_page_html($index = null)
	{
		$result = null;
		$url = "https://www.ptt.cc/bbs/{$this->board_name}/index{$index}.html";
		$context = $this->init_opts();

		$error_count = 0;
		while ($error_count < 3 && ($result = @file_get_contents($url, false, $context)) == false) {
			$this->error_output("connection error, retry... \n");
			sleep($this->config["error_sleep"]);
			$error_count++;
		}
		return $result;
	}

	// 取得當篇文章的html
	private function fetch_article_html($id)
	{
		$result = null;
		$url = "https://www.ptt.cc/bbs/{$this->board_name}/{$id}.html";
		$context = $this->init_opts();

		// 連線逾時超過三次, 回傳NULL
		$error_count = 0;
		while ($error_count < 3 && ($result = @file_get_contents($url, false, $context)) == false) {
			$headers = get_headers($url);
			$response = substr($headers[0], 9, 3);
			if ($response == "404") {
				$this->error_output("response 404..., this article will be skipped \n");
				$error_count = 4;
			} else {
				$this->error_output("connection error, retry... \n");
				sleep($this->config["error_sleep"]);
				$error_count++;
			}
		}
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
	private function save_single_list($item)
	{
		$this->db->InsertList($item, $this->board_name);
		sleep($this->config["list_sleep"]);
	}

	// 存入當篇文章的詳細資料
	private function save_single_article($insert_data)
	{
		$this->db->InsertArticle($insert_data, $this->board_name);
		sleep($this->config["article_sleep"]);
	}

	private function is_date_over($article_date)
	{
		return (strtotime(date($article_date)) <= strtotime('-1 day', strtotime($this->config["last_date"]))) ? TRUE : FALSE;
	}

	private function init_opts()
	{
		$opts = array(
			'http' => array(
				'method' => "GET",
				'timeout' => $this->config["timeout"],
				'header' => "Accept-language: zh-TW\r\n" . "Cookie: over18=1\r\n",
				'User-Agent' => "Mozilla/5.0 (Windows NT 6.3; WOW64; Trident/7.0; rv:11.0) like Gecko"
			)
		);
		return stream_context_create($opts);
	}

	private function error_output($message)
	{
		$fh = fopen('php://stderr', 'w');
		fwrite($fh, $message);
		fclose($fh);
	}
}