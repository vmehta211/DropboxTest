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
            }
        }
    }

    function listJpegs() {
        print_r($this->images);
    }

    function createPath($fromTime) {
        $t = strtotime($fromTime);
        $path = $this->config->get('baseSavePath') . $this->accountInfo['uid'] . '/' . date('Y/m/d', $t) . '/';

        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        return $path;
    }

    public function markIngestBegin() {
        $this->dbConn->markIngestStart($this->userInfo['id']);
    }

    public function markIngestComplete() {
        $this->dbConn->markIngestComplete($this->userInfo['id']);
    }

}
