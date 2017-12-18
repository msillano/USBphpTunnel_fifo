-- phpMyAdmin SQL Dump
-- version 2.11.11.3
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generato il: 12 Ago, 2017 at 09:33 AM
-- Versione MySQL: 5.0.45
-- Versione PHP: 5.2.5
-- NOTE: To be created in remotesdb  database (see file irp_config.php)

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `remotesdb`
--

-- --------------------------------------------------------

--
-- Struttura della tabella `fifo`
--

CREATE TABLE IF NOT EXISTS `fifo` (
  `id` int(11) NOT NULL auto_increment,
  `time` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `endtime` timestamp NULL default NULL,
  `type` enum('SET','GET') NOT NULL,
  `status` enum('WAIT','PROCESS','READY','DONE','BAD') NOT NULL default 'WAIT',
  `value` int(3) NOT NULL default '0' COMMENT 'field selector',
  `data` varchar(4000) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
