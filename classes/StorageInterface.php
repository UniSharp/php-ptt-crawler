<?php

interface StorageInterface {

    /**
     * Insert List
     */
    public function InsertList($array, $board_name);

    /**
     * Insert Article
     */
    public function InsertArticle($array, $board_name);

    /**
     * 判斷文章是否已存在
     */
    public function GetArticleByUrl($id);
}

