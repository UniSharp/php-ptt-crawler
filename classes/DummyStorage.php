<?php

class DummyStorage implements StorageInterface
{

	function __construct()
	{
		// todo
	}

	/**
	 * Insert List
	 */
	public function InsertList($array, $board_name)
	{
		// do nothing
	}

	/**
	 * Insert Article
	 */
	public function InsertArticle($array, $board_name)
	{
		echo "url: https://www.ptt.cc/bbs/{$board_name}/{$array["article_id"]}.html \n";
		echo "author: {$array["article_author"]} \n";
		echo "time: {$array["article_time"]} \n";
		echo "content: {$array["article_content"]} \n";
	}

	/**
	 * 判斷文章是否已存在
	 */
	public function GetArticleByUrl($id)
	{
		return false;
	}
}