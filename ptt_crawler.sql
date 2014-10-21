-- phpMyAdmin SQL Dump
-- version 4.0.2
-- http://www.phpmyadmin.net
--
-- 主機: localhost
-- 產生日期: 2014 年 10 月 21 日 02:46
-- 伺服器版本: 5.6.11-log
-- PHP 版本: 5.3.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- 資料庫: `ptt_crawler`
--
CREATE DATABASE IF NOT EXISTS `ptt_crawler` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `ptt_crawler`;

-- --------------------------------------------------------

--
-- 表的結構 `ptt_article`
--

CREATE TABLE IF NOT EXISTS `ptt_article` (
  `article_id` varchar(25) NOT NULL,
  `article_author` varchar(30) NOT NULL,
  `article_content` text NOT NULL,
  `article_time` varchar(25) NOT NULL,
  `created_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `board_name` varchar(25) NOT NULL,
  PRIMARY KEY (`article_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的結構 `ptt_list`
--

CREATE TABLE IF NOT EXISTS `ptt_list` (
  `post_id` varchar(25) NOT NULL,
  `post_board` varchar(25) NOT NULL,
  `post_title` varchar(50) NOT NULL,
  `post_date` varchar(10) NOT NULL,
  `post_author` varchar(20) DEFAULT NULL,
  `created_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`post_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
