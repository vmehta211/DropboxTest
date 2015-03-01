<?php
/*
 * 
 * This was hacked together to aid in testing the backend indexing 
 * portion of this test.
 * 
 * 
 */
require_once __DIR__ . '/dropbox-sdk/Dropbox/strict.php';
require_once __DIR__ . '/dropbox-sdk/Dropbox/autoload.php';
require_once __DIR__ . '/../config/autoloader.php';
require_once __DIR__ . '/../config/app.php';

use \Dropbox as dbx;

$requestPath = init();

session_start();

$config = Config::getInstance();

$db = new db($config->get('db'));

if ($requestPath === "/") {
    $dbxClient = getClient();

    if ($dbxClient === false) {
        header("Location: " . getPath("dropbox-auth-start"));
        exit;
    }

    $path = "/";
    if (isset($_GET['path']))
        $path = $_GET['path'];

    echo renderHtmlPage("Indexing!", "You already started the indexing process<br>"
            . "To unlink <a href='" . htmlspecialchars(getPath("dropbox-auth-unlink")) . "'>Click here!</a>.");
} else if ($requestPath === "/dpImageIndexer-status") {
    echo renderHtmlPage("Indexing!", "You already started the indexing process<br>"
            . "To view indexed files <a href='../../content/" . $_SESSION['dbJpegIndexer_uid'] . "/'>Click here!</a>.<br>"
            . "To unlink <a href='" . htmlspecialchars(getPath("dropbox-auth-unlink")) . "'>Click here!</a>.");
} else if ($requestPath === "/dropbox-auth-start") {
    $authorizeUrl = getWebAuth()->start();
    header("Location: $authorizeUrl");
} else if ($requestPath === "/dropbox-auth-finish") {
    try {
        list($accessToken, $userId, $urlState) = getWebAuth()->finish($_GET);
// We didn't pass in $urlState to finish, and we're assuming the session can't be
        // tampered with, so this should be null.
        assert($urlState === null);
    } catch (dbx\WebAuthException_BadRequest $ex) {
        respondWithError(400, "Bad Request");
        // Write full details to server error log.
        // IMPORTANT: Never show the $ex->getMessage() string to the user -- it could contain
        // sensitive information.
        error_log("/dropbox-auth-finish: bad request: " . $ex->getMessage());
        exit;
    } catch (dbx\WebAuthException_BadState $ex) {
        // Auth session expired.  Restart the auth process.
        header("Location: " . getPath("dropbox-auth-start"));
        exit;
    } catch (dbx\WebAuthException_Csrf $ex) {
        respondWithError(403, "Unauthorized", "CSRF mismatch");
        // Write full details to server error log.
        // IMPORTANT: Never show the $ex->getMessage() string to the user -- it contains
        // sensitive information that could be used to bypass the CSRF check.
        error_log("/dropbox-auth-finish: CSRF mismatch: " . $ex->getMessage());
        exit;
    } catch (dbx\WebAuthException_NotApproved $ex) {
        echo renderHtmlPage("Not Authorized?", "Why not?");
        exit;
    } catch (dbx\WebAuthException_Provider $ex) {
        error_log("/dropbox-auth-finish: unknown error: " . $ex->getMessage());
        respondWithError(500, "Internal Server Error");
        exit;
    } catch (dbx\Exception $ex) {
        error_log("/dropbox-auth-finish: error communicating with Dropbox API: " . $ex->getMessage());
        respondWithError(500, "Internal Server Error");
        exit;
    }

    $dbxClient = getClient($accessToken);
    $accountInfo = $dbxClient->getAccountInfo();

    $_SESSION['dbJpegIndexer_id'] = $db->insertAccessToken($accountInfo['display_name'], $accountInfo['email'], $accessToken, $accountInfo['uid']);
    $_SESSION['dbJpegIndexer_uid'] = $accountInfo['uid'];


    $info = $db->getUserInfo($_SESSION['dbJpegIndexer_id']);
    $message = "Indexer is currently running. Please try again later";
    if (!$info['ingesting']) {
        $message = "Starting indexer";
        startIndexing($_SESSION['dbJpegIndexer_id'], $accountInfo['uid']);
    }

    echo renderHtmlPage("You have been authorized!", "$message<br><a href='" . htmlspecialchars(getPath("dpImageIndexer-status")) . "'>click here</a> to view the status of indexing.<br>"
            . "If you want to start the auth process over , <a href='" . htmlspecialchars(getPath("dropbox-auth-unlink")) . "'>click here</a>.<br>");
} else if ($requestPath === "/dropbox-auth-unlink") {
    unset($_SESSION['dbJpegIndexer_id']);
    unset($_SESSION['dbJpegIndexer_uid']);
    echo renderHtmlPage("Unlinked.", "Go back <a href='" . htmlspecialchars(getPath("")) . "'>home</a>.");
} else {
    echo renderHtmlPage("Bad URL", "No handler for $requestPath");
    exit;
}

function getAppConfig() {
    try {
        $config = Config::getInstance();

        $appInfo = dbx\AppInfo::loadFromJson($config->get('dropboxCreds'));
        //$appInfo = dbx\AppInfo::loadFromJsonFile($appInfoFile);
    } catch (dbx\AppInfoLoadException $ex) {
        throw new Exception("Unable to load config: " . $ex->getMessage());
    }

    $clientIdentifier = $config->get('appName');
    $userLocale = null;

    return array($appInfo, $clientIdentifier, $userLocale);
}

function getClient($accessToken = null) {


    if (!isset($accessToken)) {
        return false;
    }

    list($appInfo, $clientIdentifier, $userLocale) = getAppConfig();
    return new dbx\Client($accessToken, $clientIdentifier, $userLocale, $appInfo->getHost());
}

function getWebAuth() {
    list($appInfo, $clientIdentifier, $userLocale) = getAppConfig();
    $redirectUri = getUrl("dropbox-auth-finish");
    $csrfTokenStore = new dbx\ArrayEntryStore($_SESSION, 'dropbox-auth-csrf-token');
    return new dbx\WebAuth($appInfo, $clientIdentifier, $redirectUri, $csrfTokenStore, $userLocale);
}

function renderHtmlPage($title, $body) {
    return <<<HTML
    <html>
        <head>
            <title>$title</title>
        </head>
        <body>
            <h1>$title</h1>
            $body
        </body>
    </html>
HTML;
}

function respondWithError($code, $title, $body = "") {
    $proto = $_SERVER['SERVER_PROTOCOL'];
    header("$proto $code $title", true, $code);
    echo renderHtmlPage($title, $body);
}

function getUrl($relative_path) {
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $scheme = "https";
    } else {
        $scheme = "http";
    }
    $host = $_SERVER['HTTP_HOST'];
    $path = getPath($relative_path);
    return $scheme . "://" . $host . $path;
}

function getPath($relative_path) {
    if (PHP_SAPI === 'cli-server') {
        return "/" . $relative_path;
    } else {
        return $_SERVER["SCRIPT_NAME"] . "/" . $relative_path;
    }
}

function init() {

    // For when we're running under CGI or mod_php.
    if (isset($_SERVER['PATH_INFO'])) {
        return $_SERVER['PATH_INFO'];
    } else {
        return "/";
    }
}

function startIndexing($user_id, $uid) {
    global $db, $config;
    $taskId = $db->addTask($user_id, 'buildFileList');

    $taskConfig = $config->get('tasks');
    $workers = $taskConfig['workersPerUser'];
    
    if($workers > 0){
        system("php55 runIndexer.php -userId:$user_id -runTaskId:$taskId > ../logs/log_$uid.txt &");
    }
}
