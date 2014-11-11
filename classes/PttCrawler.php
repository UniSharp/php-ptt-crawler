<?php
require 'Parser.php';

class PttCrawler
{
	private $storage = null; // Storage物件
	private $board_name = null; // 版名
	private $config = array(); // 設定參數陣列

	const STATE_DUPLICATED = 0x01;
	const STATE_DATE_REACHED = 0x02;

	public function __construct(StorageInterface $storage, $board_name)
	{
		date_default_timezone_set("Asia/Taipei");

		$this->storage = $storage;
		$this->board_name = $board_name;
		$this->set_config(null);
	}

	public function set_config($config)
	{
		// 每頁清單抓完間隔時間
		$this->config["list_sleep"] = (!isset($config["list_sleep"])) ? 0.5 : $config["list_sleep"];
		// 每篇文章抓完間隔時間
		$this->config["article_sleep"] = (!isset($config["article_sleep"])) ? 2 : $config["article_sleep"];
		// 連線失敗間隔時間
		$this->config["error_sleep"] = (!isset($config["error_sleep"])) ? 2 : $config["error_sleep"];
		// 連線送出timeout
		$this->config["timeout"] = (!isset($config["timeout"])) ? 10 : $config["timeout"];
		// 抓取文章的最後日期
		$this->config["stop-date"] = (!isset($config["stop-date"])) ? date("Y-m-d") : $config["stop-date"];
		// 設定是否只抓到上次的最後一篇
		$this->config["stop-on-duplicate"] = (!isset($config["stop-on-duplicate"])) ? true : $config["stop-on-duplicate"];
	}

	// 供外部程式呼叫執行
	public function run()
	{
		if ($this->main()) {
			return 0;
		} else {
			return 1;
		}

	}

	// 主程式邏輯
	private function main()
	{
		$state = 0;
		$is_stop = false;
		// 取得總頁數
		$last_page = $this->page_count();

		for ($i = $last_page; $i >= 1; $i--) {
			// 檢查爬蟲是否該繼續爬資料
			if ($is_stop) break;
			// 取得每頁文章基本資料
			$current_page = $this->fetch_page($i);

			// 過濾失敗文章
			if ($current_page == null) {
				$this->error_output("notice! list: " . $i . " was skipped \n");
				continue;
			}

			$save_article_arr = array();
			foreach ($current_page as $item) {
				// 略過已抓過的文章
				if ($this->storage->GetArticleByUrl($item["url"])) {
					$this->error_output("notice! article: " . $item["url"] . " has been in database \n");
					// 檢查是否抓到上次最後一篇
					if ($this->config["stop-on-duplicate"]) {
						$is_stop = true;
						$state |= self::STATE_DUPLICATED;
					}
					continue;
				}
				// 略過已到設定日期文章
				if ($this->is_date_over($item["date"])) {
					$this->error_output("notice! article: " . $item["url"] . " is earlier than " . $this->config["stop-date"] . " \n");
					$is_stop = true;
					$state |= self::STATE_DATE_REACHED;
					continue;
				}
				// 存入要抓取詳細資料的article陣列
				array_push($save_article_arr, $item);
				// 存入每頁文章基本資料
				try {
					$this->storage->InsertList($item, $this->board_name);
				} catch (PDOException $e) {
					if ($e->errorInfo[1] == SERVER_SHUTDOWN_CODE) {
						exit("mysql server connection error!");
						// todo
					}
				}
				sleep($this->config["list_sleep"]);
			}

			foreach ($save_article_arr as $item) {
				$this->error_output("fetching article id: " . $item["url"] . "\n");
				// 取得每筆文章詳細資料
				$article = $this->fetch_article($item["url"]);
				// 過濾詭異文章
				if ($article == null) {
					$this->error_output("notice! article: " . $item["url"] . " was skipped \n");
					continue;
				}
				// 存入每筆文章詳細資料(returned id)
				try {
					$this->storage->InsertArticle($article, $this->board_name);
				} catch (PDOException $e) {
					if ($e->errorInfo[1] == SERVER_SHUTDOWN_CODE) {
						exit("mysql server connection error!");
						// todo
					}
				}
				sleep($this->config["article_sleep"]);
			}
		}
		// 檢測文章是否到期
		if ($state & self::STATE_DATE_REACHED) {
			$this->error_output("Stop fetching cause the article date are older than " . $this->config["stop-date"] . "\n");
		}

		// 檢查是否已重複
		if ($state & self::STATE_DUPLICATED) {
			$this->error_output("Stop fetching cause the articles are duplicated. \n");
		}

		$this->error_output("Fetch finished! \n");
		return true;
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
		if ($dom == null) {
			return null;
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

		// 連線逾時超過三次, 回傳NULL
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
			$response = substr(@$http_response_header[0], 9, 3);
			if ($response == "404") {
				$this->error_output("response 404..., this article will be skipped \n");
				break;
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
		if ($dom == null) {
			return $dom;
		}
		$result = array();

		// 取得文章內容
		foreach ($dom->find('div[id=main-container]') as $element) {
			$content = strip_tags(trim($element));

			$result["article_id"] = $id;
			$pos_1 = strpos($content, "作者");
			$pos_2 = strpos($content, "看板");
			$result["article_author"] = substr($content, $pos_1 + 6, $pos_2 - 11);

			$pos_1 = strpos($content, "時間");
			$result["article_time"] = substr($content, $pos_1 + 6, 24);

			$pos_1 = strpos($content, @$result["article_time"]);
			$pos_2 = strpos($content, "※ 發信站");
			$result["article_content"] = substr($content, $pos_1 + strlen(@$result["article_time"]) + 1, $pos_2 - $pos_1 - 28);
			$result["article_content"] = str_replace(
				array('&#34;', '&lt;' ,'&gt;'),
				array('"', '<', '>'),
				$result["article_content"]);
		}

		// 過濾詭異文章
		if (!isset($result["article_id"])) {
			return null;
		}
		return $result;
	}

	private function is_date_over($article_date)
	{
		return (strtotime(date($article_date)) <= strtotime('-1 day', strtotime($this->config["stop-date"]))) ? true : false;
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