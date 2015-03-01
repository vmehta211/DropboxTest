ALTER TABLE `dbImageIndexer_users` CHANGE `user_id` `user_id` INT NOT NULL AUTO_INCREMENT ;

CREATE TABLE `dbImageIndexer_tasks` (
 `task_id` int(11) NOT NULL AUTO_INCREMENT,
 `user_id` int(11) NOT NULL,
 `type` enum('buildFileList','downloadAndIndex') NOT NULL,
 `data` longtext,
 `completed` tinyint(1) NOT NULL DEFAULT '0',
 `date_added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
 `date_started` timestamp NULL DEFAULT NULL,
 `date_complete` timestamp NULL DEFAULT NULL,
 `worker_id` int(11) DEFAULT NULL,
 `pid` int(11) DEFAULT NULL,
 `taken` tinyint(1) NOT NULL DEFAULT '0',
 PRIMARY KEY (`task_id`),
 KEY `user_id` (`user_id`),
 KEY `user_id_2` (`user_id`),
 KEY `user_id_3` (`user_id`),
 KEY `completed` (`completed`),
 KEY `taken` (`taken`),
 CONSTRAINT `users` FOREIGN KEY (`user_id`) REFERENCES `dbImageIndexer_users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

