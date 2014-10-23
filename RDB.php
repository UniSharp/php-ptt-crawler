<?php
require 'Database.php';

class RDB extends Database
{

	function __construct()
	{
		parent::__construct();
	}

	/**
     * Insert List
     */
    public function InsertList($array, $board)
    {
        $sql = "INSERT INTO ptt_list (post_id, post_board, post_title, post_date, post_author) VALUES (:post_id, :post_board, :post_title, :post_date, :post_author)";
        $bind["post_id"] = $array["url"];
        $bind["post_board"] = $board;
        $bind["post_title"] = $array["title"];
        $bind["post_date"] = $array["date"];
        $bind["post_author"] = $array["author"];
        $query = $this->db->prepare($sql);
        $query->execute($bind);
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
        $query = $this->db->prepare($sql);
        $query->execute($bind);
    }

    /**
     * 判斷文章是否已存在
     */
    public function IsArticle($id)
    {
        $sql = "SELECT post_id FROM ptt_list WHERE post_id = :post_id";
        $bind["post_id"] = $id;
        $query = $this->db->prepare($sql);
        $query->execute($bind);

        $result = $query->fetchAll();

        return (count($result) == 0) ? FALSE : TRUE;
    }
}