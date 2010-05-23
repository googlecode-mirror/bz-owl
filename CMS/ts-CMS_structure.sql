# Sequel Pro dump
# Version 2210
# http://code.google.com/p/sequel-pro
#
# Host: localhost (MySQL 5.1.46)
# Database: ts-CMS
# Generation Time: 2010-05-23 12:46:07 +0200
# ************************************************************

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Dump of table bans
# ------------------------------------------------------------

DROP TABLE IF EXISTS `bans`;

CREATE TABLE `bans` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `timestamp` varchar(20) NOT NULL DEFAULT '0000-00-00 00:00:00',
  `author` varchar(255) DEFAULT NULL,
  `announcement` text,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=16 DEFAULT CHARSET=utf8;



# Dump of table invitations
# ------------------------------------------------------------

DROP TABLE IF EXISTS `invitations`;

CREATE TABLE `invitations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invited_playerid` int(11) NOT NULL DEFAULT '0',
  `teamid` int(11) NOT NULL DEFAULT '0',
  `expiration` varchar(20) NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;



# Dump of table matches
# ------------------------------------------------------------

DROP TABLE IF EXISTS `matches`;

CREATE TABLE `matches` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `timestamp` varchar(20) NOT NULL DEFAULT '0000-00-00 00:00:00',
  `team1_teamid` int(11) unsigned NOT NULL DEFAULT '0',
  `team2_teamid` int(11) unsigned NOT NULL DEFAULT '0',
  `team1_points` int(11) NOT NULL DEFAULT '0',
  `team2_points` int(11) NOT NULL DEFAULT '0',
  `team1_new_score` int(11) NOT NULL DEFAULT '1200',
  `team2_new_score` int(11) NOT NULL DEFAULT '1200',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=15 DEFAULT CHARSET=utf8 COMMENT='The played matches in the league';



# Dump of table messages_storage
# ------------------------------------------------------------

DROP TABLE IF EXISTS `messages_storage`;

CREATE TABLE `messages_storage` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `author` varchar(255) NOT NULL,
  `author_id` int(11) NOT NULL,
  `subject` varchar(50) NOT NULL,
  `timestamp` varchar(20) NOT NULL DEFAULT '0000-00-00 00:00:00',
  `message` varchar(2000) NOT NULL,
  `from_team` tinyint(1) unsigned NOT NULL,
  `recipients` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=119 DEFAULT CHARSET=utf8 COMMENT='The message storage';



# Dump of table messages_team_connection
# ------------------------------------------------------------

DROP TABLE IF EXISTS `messages_team_connection`;

CREATE TABLE `messages_team_connection` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `msgid` int(11) unsigned NOT NULL,
  `teamid` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;



# Dump of table messages_users_connection
# ------------------------------------------------------------

DROP TABLE IF EXISTS `messages_users_connection`;

CREATE TABLE `messages_users_connection` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `msgid` int(11) unsigned NOT NULL,
  `playerid` int(11) NOT NULL,
  `in_inbox` tinyint(1) NOT NULL,
  `in_outbox` tinyint(1) NOT NULL,
  `msg_unread` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=250 DEFAULT CHARSET=utf8 COMMENT='Connects messages to users';



# Dump of table news
# ------------------------------------------------------------

DROP TABLE IF EXISTS `news`;

CREATE TABLE `news` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `timestamp` varchar(20) NOT NULL DEFAULT '0000-00-00 00:00:00',
  `author` varchar(255) DEFAULT NULL,
  `announcement` text,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=24 DEFAULT CHARSET=utf8;



# Dump of table online_users
# ------------------------------------------------------------

DROP TABLE IF EXISTS `online_users`;

CREATE TABLE `online_users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `playerid` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `last_activity` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=168 DEFAULT CHARSET=utf8 COMMENT='list of online users';



# Dump of table players
# ------------------------------------------------------------

DROP TABLE IF EXISTS `players`;

CREATE TABLE `players` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `external_playerid` varchar(50) NOT NULL,
  `teamid` int(11) unsigned NOT NULL DEFAULT '0',
  `name` varchar(50) NOT NULL,
  `last_teamid` int(11) unsigned NOT NULL DEFAULT '0',
  `suspended` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `external_playerid` (`external_playerid`)
) ENGINE=MyISAM AUTO_INCREMENT=34 DEFAULT CHARSET=utf8 COMMENT='suspended: 0 active; 1 maint-deleted; 2 disabled; 3 banned';



# Dump of table players_passwords
# ------------------------------------------------------------

DROP TABLE IF EXISTS `players_passwords`;

CREATE TABLE `players_passwords` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `playerid` int(11) unsigned NOT NULL DEFAULT '0',
  `password` varchar(32) NOT NULL DEFAULT '',
  `password_md5_encoded` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;



# Dump of table players_profile
# ------------------------------------------------------------

DROP TABLE IF EXISTS `players_profile`;

CREATE TABLE `players_profile` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `playerid` int(11) unsigned NOT NULL DEFAULT '0',
  `location` varchar(50) NOT NULL DEFAULT 'Here be dragons',
  `user_comment` varchar(500) NOT NULL DEFAULT '',
  `admin_comments` varchar(1000) NOT NULL DEFAULT '',
  `last_visit` varchar(20) NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logo_url` varchar(200) DEFAULT NULL,
  `joined` varchar(20) NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=27 DEFAULT CHARSET=utf8 COMMENT='the players profile data';



# Dump of table teams
# ------------------------------------------------------------

DROP TABLE IF EXISTS `teams`;

CREATE TABLE `teams` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(30) NOT NULL DEFAULT 'think of a good name',
  `leader_playerid` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=168 DEFAULT CHARSET=utf8;



# Dump of table teams_overview
# ------------------------------------------------------------

DROP TABLE IF EXISTS `teams_overview`;

CREATE TABLE `teams_overview` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `teamid` int(11) NOT NULL DEFAULT '0',
  `score` int(11) NOT NULL DEFAULT '1200',
  `member_count` int(11) unsigned NOT NULL DEFAULT '1',
  `any_teamless_player_can_join` tinyint(1) NOT NULL DEFAULT '1',
  `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8 COMMENT='deleted: 0 new; 1 active; 2 deleted; 3 re-activated';



# Dump of table teams_permissions
# ------------------------------------------------------------

DROP TABLE IF EXISTS `teams_permissions`;

CREATE TABLE `teams_permissions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `teamid` int(11) NOT NULL DEFAULT '0',
  `locked_by_admin` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8;



# Dump of table teams_profile
# ------------------------------------------------------------

DROP TABLE IF EXISTS `teams_profile`;

CREATE TABLE `teams_profile` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teamid` int(11) NOT NULL DEFAULT '0',
  `num_matches_played` int(11) NOT NULL DEFAULT '0',
  `num_matches_won` int(11) NOT NULL DEFAULT '0',
  `num_matches_draw` int(11) NOT NULL DEFAULT '0',
  `num_matches_lost` int(11) NOT NULL DEFAULT '0',
  `description` varchar(3000) NOT NULL DEFAULT 'Think of a good description',
  `logo_url` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;



# Dump of table visits
# ------------------------------------------------------------

DROP TABLE IF EXISTS `visits`;

CREATE TABLE `visits` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `playerid` int(11) unsigned NOT NULL DEFAULT '0',
  `ip-address` varchar(200) NOT NULL DEFAULT '0.0.0.0.0',
  `host` varchar(100) DEFAULT NULL,
  `timestamp` varchar(20) NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=68 DEFAULT CHARSET=utf8;






/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
