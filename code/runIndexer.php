#!/usr/bin/env php
<?php
if (PHP_SAPI !== "cli") {
    throw new \Exception("This program was meant to be run from the command-line and not as a web app.  Bad value for PHP_SAPI.  Expected \"cli\", given \"".PHP_SAPI."\".");
}
require_once __DIR__ . "/dropbox-sdk/Dropbox/strict.php";
require_once __DIR__ . '/dropbox-sdk/Dropbox/autoload.php';
require_once __DIR__ . '/../config/app.php'; //config class
require_once __DIR__ . '/../config/autoloader.php';

//require_once __DIR__ . '/classes/dbIndexer.php'; //dropbox indexer class
//require_once __DIR__ . '/classes/db.php'; //database class

use \Dropbox as dbx;

$config = Config::getInstance();

$db = new db($config->get('db'));

if(isset($argv[1])){
    $userInfo = $db->getUserInfo($argv[1]);
    $config->set('authInfo', $userInfo);
}


try {
    list($accessToken, $host) = dbx\AuthInfo::loadFromJson($config->get('authInfo'));
} catch (dbx\AuthInfoLoadException $ex) {
    fwrite(STDERR, "Error loading <auth-file>: " . $ex->getMessage() . "\n");
    die;
}

$client = new dbx\Client($accessToken, $config->get('appName'));

//$accountInfo = $client->getAccountInfo();
//print_r($accountInfo);

$indexer = new dbIndexer($client, $config, $db, $userInfo);
$indexer->markIngestBegin();
$indexer->collectImages('/Photos/drone');
//$indexer->listJpegs();
$indexer->downloadAndIndex();
$indexer->markIngestComplete();



