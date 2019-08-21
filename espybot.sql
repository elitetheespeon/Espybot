--
-- Table structure for table `config`
--

DROP TABLE IF EXISTS `config`;
CREATE TABLE `config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(45) CHARACTER SET utf8mb4 NOT NULL,
  `value1` text COLLATE utf8mb4_unicode_ci,
  `value2` text COLLATE utf8mb4_unicode_ci,
  `value3` text COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `bot_id` int(2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `values` (`bot_id`,`value1`(40),`value2`(40),`value3`(40))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `invites`
--

DROP TABLE IF EXISTS `invites`;
CREATE TABLE `invites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guild` varchar(50) NOT NULL,
  `code` varchar(50) NOT NULL,
  `maxage` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `uses` int(11) DEFAULT NULL,
  `inviter_id` varchar(50) DEFAULT NULL,
  `channel_id` varchar(50) DEFAULT NULL,
  `bot_id` int(2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Table structure for table `last_seen`
--

DROP TABLE IF EXISTS `last_seen`;
CREATE TABLE `last_seen` (
  `user_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_online` datetime DEFAULT NULL,
  `desc` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_spoke` datetime DEFAULT NULL,
  `bot_id` int(2) NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `user_id_UNIQUE` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `logs`
--

DROP TABLE IF EXISTS `logs`;
CREATE TABLE `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guild` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `user` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `channel` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bot_id` int(2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `channel` (`bot_id`,`guild`,`channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `name_history`
--

DROP TABLE IF EXISTS `name_history`;
CREATE TABLE `name_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_name` varchar(170) CHARACTER SET utf8mb4 NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `bot_id` int(2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_name_UNIQUE` (`user_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `steam_log`
--

DROP TABLE IF EXISTS `steam_log`;
CREATE TABLE `steam_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `group_name` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `steam_id` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_name` varchar(80) CHARACTER SET utf8mb4 DEFAULT NULL,
  `is_pm` tinyint(1) NOT NULL DEFAULT '0',
  `message` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `timers`
--

DROP TABLE IF EXISTS `timers`;
CREATE TABLE `timers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guild_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `channel_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_channel_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_channel_desc` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `bot_id` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
