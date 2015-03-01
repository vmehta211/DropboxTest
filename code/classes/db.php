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
            $stmt = $this->con->prepare("INSERT INTO dbImageIndexer_users(name,email,accessToken, uid) VALUES(:name,:email,:accessToken, :uid ) ON DUPLICATE KEY UPDATE reAuthCount=reAuthCount+1, lastAuth=NOW()");
            $stmt->execute(array(':name' => $name, ':email' => $email, ':accessToken' => $accessToken, ':uid' => $uid));
            return $this->con->lastInsertId();
        } catch (Exception $e) {
            error_log('db error' . $e->getMessage());
        }
    }

    function getUserInfo($user_id) {
        try {
            $statement = $this->con->prepare("SELECT * FROM `dbImageIndexer_users` WHERE `id` = :id");
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
        $statement = $this->con->prepare("UPDATE `dbImageIndexer_users` SET `ingestStart` = now(), ingestEnd = NULL, ingesting = 1");
        $statement->execute();
    }
    
    function markIngestComplete($user_id) {
        $statement = $this->con->prepare("UPDATE `dbImageIndexer_users` SET `ingestEnd` = now(), ingesting = 0");
        $statement->execute();
    }
}
