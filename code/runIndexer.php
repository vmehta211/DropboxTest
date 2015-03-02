#!/usr/bin/env php
<?php
if (PHP_SAPI !== "cli") {
    throw new \Exception("This program was meant to be run from the command-line and not as a web app.  Bad value for PHP_SAPI.  Expected \"cli\", given \"" . PHP_SAPI . "\".");
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

$args = parseArgs($argv);

if (isset($args['userId'])) {
    $userInfo = $db->getUserInfo($args['userId']);
} else {
    $userInfo = $db->getUserInfoForUserWithOpenTask();
    if ($userInfo === false) {
        die('There are no uncomplete and untaken tasks to work on');
    } else {
        $args['userId'] = $userInfo['user_id'];
        echo "Found a task to work on for user " . $args['userId'] . "\n";
    }
}

$config->set('authInfo', $userInfo);

if (isset($args['runTaskId'])) {
    $task = $db->getTask($args['runTaskId']);
} else {
    $task = $db->getTasks($args['userId'], 1);
    $task = $task[0];
}

$taskConfig = $config->get('tasks');
$retry = $taskConfig['taskRaceConditionRetry'];

//this is avoid race condition caused by spawning workers at the same time
while ($db->markTaskStarted($task['task_id'], getmypid(), $config->get('workerId')) === false && $retry--) {
    //usleep(100000 * $retry);

    //TODO - if the above is never able to mark the task started don't continue
    $task = $db->getTasks($args['userId'], 1);
    $task = $task[0];
}


echo "printing task\n";
print_r($task);


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

switch ($task['type']) {
    case 'buildFileList':
        $indexer->markIngestBegin();
        echo "Starting buildFileList\n";
        $indexer->collectImages($config->get('dbIngestRoot'));
        $imageCount = $indexer->getImageCount();
        $indexer->saveImageCount($imageCount);
        echo "There are $imageCount images\n";

        if ($imageCount < $taskConfig['minBreakFileCount']) {
            $indexer->downloadAndIndex();
            $indexer->markIngestComplete(); //will check if it's the last task 
        } else {
            $indexer->saveImagesByTask($taskConfig['workersPerUser']);
            $indexer->spawnWorkers($taskConfig['workersPerUser']);
        }

        $indexer->markTaskComplete($task['task_id']);

        break;
    case 'downloadAndIndex':
        $indexer->setImages(unserialize($task['data']));
        $indexer->downloadAndIndex();

        echo "completed all downloading and indexing operations\n";

        $indexer->markTaskComplete($task['task_id']);

        if ($indexer->allTaskComplete()) {
            echo "All Tasks are finished!!!\n";
            $indexer->markIngestComplete(); //will check if it's the last task 
        }

        break;
    default:
        throw Exception('invalid task type ' . $args['runTask']);
}

function parseArgs($args) {
    foreach ($args as $arg) {
        $tmp = explode(':', $arg, 2);
        if ($arg[0] === '-') {
            $args[substr($tmp[0], 1)] = $tmp[1];
        }
    }
    return $args;
}
