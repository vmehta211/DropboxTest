<?php

/*
 * 
 * This is the main worker class
 * 
 * 
 */

class dbIndexer {

    var $accessToken, $name, $id, $email;
    var $images = array(), $folders = array();
    var $dbClient;
    var $dbConn;
    var $config;
    var $accountInfo;
    var $noDateTakenFolder;
    var $userInfo; //dbImageIndexer_user row

    function __construct($dbClient, $config, db $database, $userInfo) {
        $this->dbClient = $dbClient;
        $this->accountInfo = $dbClient->getAccountInfo();
        $this->dbConn = $database;
        $this->userInfo = $userInfo;

        print_r($this->accountInfo);

        $this->config = $config;
        //create directory for uid
        $dir = $this->config->get('baseSavePath') . '/' . $this->accountInfo['uid'];
        if (!is_dir($dir)) {
            mkdir($dir);
        }

        $this->noDateTakenFolder = $dir . '/' . $config->get('noDateTakenFolder') . '/';
        if (!is_dir($this->noDateTakenFolder)) {
            mkdir($this->noDateTakenFolder);
        }
    }

    /**
     * 
     * @param string $folder 
     * 
     * parse folder. if jpeg add to image queue. if folder add to folder queue.
     * if there are folders in the queue, remove one and parse it
     */
    function collectImages($folder) {
        $metadata = $this->dbClient->getMetadataWithChildren($folder);

        foreach ($metadata['contents'] as $m) {
            if ($m['is_dir']) {
                $this->folders[] = $m['path'];
            } else if (isset($m['mime_type']) && $m['mime_type'] == 'image/jpeg') {
                $this->images[] = $m['path'];
            }
        }

        unset($metadata);

        if (count($this->folders)) {
            $folder = array_shift($this->folders);
            $this->collectImages($folder);
        }
    }

    /**
     * 
     * iterate through the list of paths, download the files, check exif, and index
     */
    function downloadAndIndex() {
        foreach ($this->images as $im) {
            $fn = $this->config->get('tmpPath') . $this->accountInfo['uid'] . '_' . md5($im) . '.jpg';
            $metadata = $this->dbClient->getFile($im, fopen($fn, "wb"));
            if ($metadata !== null) {
                //print_r($metadata);
                //echo "File contents written to \"$fn\"\n";
                try {
                    $exif = exif_read_data($fn);
                    $p = explode('/', $im);
                    $imageName = end($p);

                    if (isset($exif['DateTime']) && !empty($exif['DateTime'])) {
                        //create path from datetime
                        $newPath = $this->createPath($exif['DateTime']);
                        $newFilename = $newPath . $imageName;
                        rename($fn, $newFilename);
                    } else {
                        $newFilename = $this->noDateTakenFolder . $imageName;
                        rename($fn, $newFilename);
                    }
                    echo "rename $fn to $newFilename\n";
                } catch (Exception $e) {
                    echo "There was an error getting exif data from $fn " . $e->getMessage() . "\n";
                }
            }
        }
    }

    function setImages($images) {
        $this->images = $images;
    }

    public function listJpegs() {
        print_r($this->images);
    }

    private function createPath($fromTime) {
        $t = strtotime($fromTime);
        $path = $this->config->get('baseSavePath') . $this->accountInfo['uid'] . '/' . date('Y/m/d', $t) . '/';

        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        return $path;
    }

    public function markIngestBegin() {
        $this->dbConn->markIngestStart($this->userInfo['user_id']);
    }

    public function markIngestComplete() {
        $this->dbConn->markIngestComplete($this->userInfo['user_id']);
    }

    public function markTaskComplete($taskId) {
        $this->dbConn->markTaskComplete($taskId);
    }

    public function getImageCount() {
        return count($this->images);
    }

    public function saveImageCount($count) {
        $this->dbConn->saveImageCount($this->userInfo['user_id'], $count);
    }

    public function saveImagesByTask($maxTaskCount) {
        $size = round($this->getImageCount() / $maxTaskCount);
        $chunked = array_chunk($this->images, $size);
        foreach ($chunked as $chunk) {
            $this->dbConn->addTask($this->userInfo['user_id'], 'downloadAndIndex', $chunk);
        }
    }

    public function allTaskComplete() {
        if ($this->dbConn->taskCount($this->userInfo['user_id']) > 0) {
            return false;
        } else {
            return true;
        }
    }

    public function spawnWorkers($workerCount) {
        $user_id = $this->userInfo['user_id'];
        $uid = $this->accountInfo['uid'];
        for ($i = 0; $i <= $workerCount; $i++) {
            $log_id = $uid.'_'.$i;
            $cmd = "php55 runIndexer.php -userId:$user_id > ../logs/log_$log_id.txt &";
            echo "running $cmd\n";
            system($cmd);
        }
    }

}
