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
		$sql = 'INSERT INTO list (id, forum, title, `date`, author) VALUES (:id, :forum, :title, :date, :author)';
		$bind["id"] = $array["url"];
		$bind["forum"] = $board_name;
		$bind["title"] = $array["title"];
		$bind["date"] = $array["date"];
		$bind["author"] = $array["author"];
		try {
			$query = $this->db->prepare($sql);
			$query->execute($bind);
		} catch (PDOException $e) {
			// FIXME useless trowing exception.
			throw $e;
		}
	}

	/**
	 * Insert Article
	 */
	public function InsertArticle($article_array, $board_name)
	{
		$sql = "INSERT INTO article (id, forum, author, content, `time`) VALUES (:id, :forum, :author, :content, :time)";
		$bind["id"] = $article_array["id"];
		$bind["forum"] = $board_name;
		$bind["author"] = $article_array["author"];
		$bind["content"] = $article_array["content"];
		$bind["time"] = $article_array["time"];

		$count = 0;
		while ($count < 3) {
			try {
				$query = $this->db->prepare($sql);
				$query->execute($bind);
				$count = 3; // FIXME weird logic here
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

	public function InsertComments($article_id, $comment_array)
	{
		foreach ($comment_array as $item) {
			$sql = 'INSERT INTO `comment` (article_id, `type`, content, `time`, author) VALUES (:article_id, :type, :content, :time, :author)';
			$bind["article_id"] = $article_id;
			$bind["type"] = $item["type"];
			$bind["author"] = $item["author"];
			$bind["content"] = $item["content"];
			$bind["time"] = $item["time"];
			$count = 0;
			while ($count < 3) {
				try {
					$query = $this->db->prepare($sql);
					$query->execute($bind);
					break;
				} catch (PDOException $e) {
					if ($e->errorInfo[1] == SERVER_SHUTDOWN_CODE) {
						$this->reconnectPDO();
					}
					$count++;
				}
			}
		}

		return $this->db->lastInsertId();
	}

	public function GetArticleByArticleId($article_id)
	{
		$sql = "SELECT * FROM article WHERE id = :article_id";
		$bind["article_id"] = $article_id;

		try {
			$query = $this->db->prepare($sql);
			$query->execute($bind);
		} catch (PDOException $e) {
			throw $e;
		}
		$result = $query->fetch(PDO::FETCH_ASSOC);
		return $result;
	}
}