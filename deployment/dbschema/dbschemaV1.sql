CREATE TABLE `dbImageIndexer_users` (
 `id` smallint(6) NOT NULL AUTO_INCREMENT,
 `name` varchar(128) NOT NULL,
 `email` varchar(75) NOT NULL,
 `accessToken` varchar(150) NOT NULL,
 `uid` int(11) NOT NULL,
 `reAuthCount` int(11) NOT NULL,
 `lastAuth` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
 `ingesting` tinyint(1) NOT NULL DEFAULT '0',
 `ingestStart` timestamp NULL DEFAULT '0000-00-00 00:00:00',
 `ingestEnd` timestamp NULL DEFAULT '0000-00-00 00:00:00',
 PRIMARY KEY (`id`),
 UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8