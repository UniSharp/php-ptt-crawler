<?php

class RDBStorage extends Database implements StorageInterface
{

	function __construct()
	{
		parent::__construct();
	}

	/**
	 * Insert List
	 */
	public function InsertList($array, $board_name)
	{
		$sql = "INSERT INTO ptt_list (post_id, post_board, post_title, post_date, post_author) VALUES (:post_id, :post_board, :post_title, :post_date, :post_author)";
		$bind["post_id"] = $array["url"];
		$bind["post_board"] = $board_name;
		$bind["post_title"] = $array["title"];
		$bind["post_date"] = $array["date"];
		$bind["post_author"] = $array["author"];
		try {
			$query = $this->db->prepare($sql);
			$query->execute($bind);
		} catch (PDOException $e) {
			throw $e;
		}
	}

	/**
	 * Insert Article
	 */
	public function InsertArticle($array, $board_name)
	{
		$sql = "INSERT INTO ptt_article (article_id, board_name, article_author, article_content, article_time) VALUES (:article_id, :board_name, :article_author, :article_content, :article_time)";
		$bind["article_id"] = $array["article_id"];
		$bind["board_name"] = $board_name;
		$bind["article_author"] = $array["article_author"];
		$bind["article_content"] = $array["article_content"];
		$bind["article_time"] = $array["article_time"];

		$count = 0;
		while($count < 3) {
			try {
				$query = $this->db->prepare($sql);
				$query->execute($bind);
				$count = 3;
			} catch (PDOException $e) {
				if ($e->errorInfo[1] == SERVER_SHUTDOWN_CODE) {
						$count++;
						$this->reconnectPDO();
					}
				throw $e;
			}
		}

		return $this->db->lastInsertId();
	}

	/**
	 * 判斷文章是否已存在
	 */
	public function GetArticleByUrl($id)
	{
		$sql = "SELECT COUNT(post_id) as count FROM ptt_list WHERE post_id = :post_id";
		$bind["post_id"] = $id;

		try {
			$query = $this->db->prepare($sql);
			$query->execute($bind);
		} catch (PDOException $e) {
			throw $e;
		}
		$result = $query->fetch();

		return $result->count;
	}
}