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
		$bind["id"] = $article_array["article_id"];
		$bind["forum"] = $board_name;
		$bind["author"] = $article_array["article_author"];
		$bind["content"] = $article_array["article_content"];
		$bind["time"] = $article_array["article_time"];

		$count = 0;
		while($count < 3) {
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

	/**
	 * 判斷文章是否已存在
	 * FIXME werid in sementic
	 */
	public function GetArticleByUrl($id)
	{
		$sql = "SELECT COUNT(id) as count FROM list WHERE id = :id";
		$bind["id"] = $id;

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