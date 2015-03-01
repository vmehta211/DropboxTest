<?php

/*
 * This class manages access to the database
 * 
 * 
 */

class db {

    var $con;

    function __construct($config) {
        try {
            $this->con = new PDO('mysql:host=' . $config['host'] . ';dbname=' . $config['dbname'] . ';charset=utf8', $config['username'], $config['password'], array(PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        } catch (Exception $e) {
            die('could not connect to db ' . $e->getMessage());
        }
    }

    function insertAccessToken($name, $email, $accessToken, $uid) {
        try {
            $stmt = $this->con->prepare("INSERT INTO dbImageIndexer_users(name,email,accessToken, uid) VALUES(:name,:email,:accessToken, :uid ) ON DUPLICATE KEY UPDATE reAuthCount=reAuthCount+1, lastAuth=NOW(), accessToken=:aToken");
            $stmt->execute(array(':name' => $name, ':email' => $email, ':accessToken' => $accessToken, ':uid' => $uid, ':aToken' => $accessToken));
            return $this->con->lastInsertId();
        } catch (Exception $e) {
            error_log('db error' . $e->getMessage());
        }
    }

    function getUserInfo($user_id) {
        try {
            $statement = $this->con->prepare("SELECT * FROM `dbImageIndexer_users` WHERE `user_id` = :id");
            $statement->execute(array(':id' => $user_id));
            $t = $statement->fetchAll();
            $t = $t[0];
            $t['access_token'] = $t['accessToken'];
            return $t;
        } catch (Exception $e) {
            error_log('db error' . $e->getMessage());
        }
    }

    function markIngestStart($user_id) {
        $statement = $this->con->prepare("UPDATE `dbImageIndexer_users` SET `ingestStart` = now(), ingestEnd = NULL, ingesting = 1 WHERE user_id = :user_id");
        $statement->execute(array(':user_id' => $user_id));
    }

    function markIngestComplete($user_id) {
        $statement = $this->con->prepare("UPDATE `dbImageIndexer_users` SET `ingestEnd` = now(), ingesting = 0 WHERE user_id = :user_id");
        $statement->execute(array(':user_id' => $user_id));
    }
    
    function saveImageCount($user_id, $image_count){
        $statement = $this->con->prepare("UPDATE `dbImageIndexer_users` SET `imageCount`=:image_count WHERE user_id = :user_id");
        $statement->execute(array(':user_id' => $user_id, ':image_count'=>$image_count));
    }

    function addTask($user_id, $type, $data = null) {
        try {

            if ($data != null) {
                $data = serialize($data);
            }

            $stmt = $this->con->prepare("INSERT INTO dbImageIndexer_tasks (user_id,type,date_added,data) VALUES(:user_id,:type,now(),:data )");
            $stmt->execute(array(':user_id' => $user_id, ':type' => $type, ':data' => $data));
            return $this->con->lastInsertId();
        } catch (Exception $e) {
            error_log('db error' . $e->getMessage());
        }
    }

    function getTask($task_id) {
        try {
            $statement = $this->con->prepare("SELECT * FROM `dbImageIndexer_tasks` WHERE `task_id` = :task_id");
            $statement->execute(array(':task_id' => $task_id));
            return $statement->fetch();
        } catch (Exception $e) {
            error_log('db error' . $e->getMessage());
        }
    }

    function getTasks($user_id, $limit = null) {
        try {

            if ($limit != null) {
                $limit = ' LIMIT ' . $limit;
            } else {
                $limit = '';
            }

            $statement = $this->con->prepare("SELECT * FROM `dbImageIndexer_tasks` WHERE `user_id` = :user_id AND completed = 0 AND date_started IS NULL $limit");
            $statement->execute(array(':user_id' => $user_id));
            return $statement->fetchAll();
        } catch (Exception $e) {
            error_log('db error' . $e->getMessage());
        }
    }

    function taskCount($user_id) {
        try {
            $statement = $this->con->prepare("SELECT count(1) FROM `dbImageIndexer_tasks` WHERE `user_id` = :user_id AND completed = 0");
            $statement->execute(array(':user_id' => $user_id));
            $row = $statement->fetch(PDO::FETCH_NUM);
            
            echo "taskCount: $row[0]\n";
            
            return $row[0];
        } catch (Exception $e) {
            error_log('db error' . $e->getMessage());
        }
    }

    function markTaskComplete($task_id) {
        try {
            echo "marking task complete: $task_id\n";
            $statement = $this->con->prepare("UPDATE `dbImageIndexer_tasks` SET completed=1, date_complete=NOW() WHERE `task_id` = :task_id");
            $statement->execute(array(':task_id' => $task_id));
        } catch (Exception $e) {
            error_log('db error' . $e->getMessage());
        }
    }

    function markTaskStarted($task_id, $pid, $worker_id) {
        try {
            echo "marking task started: $task_id by $worker_id\n";

            $statement = $this->con->prepare("UPDATE `dbImageIndexer_tasks` SET taken=1 WHERE `task_id` = :task_id");
            $statement->execute(array(':task_id' => $task_id));

            if ($statement->rowCount() == 0) {
                return false;
            }

            $statement = $this->con->prepare("UPDATE `dbImageIndexer_tasks` SET date_started=NOW(), pid = :pid, worker_id = :worker_id, taken=1 WHERE `task_id` = :task_id");
            $statement->execute(array(':task_id' => $task_id, ':pid' => $pid, ':worker_id' => $worker_id));

            return true;
        } catch (Exception $e) {
            error_log('db error' . $e->getMessage());
        }
    }

}
