<?php

class Config {

    static private $_appOptions = array(
        'appName' => 'JpegIndexer',
        'baseSavePath' => '/home/piptastic/webapps/viral_appledecay/dropboximageindexer/content/',
        'tmpPath' => '/home/piptastic/webapps/viral_appledecay/dropboximageindexer/tmp/',
        'noDateTakenFolder' => 'noTakenDate',
        'db' => array(
            'host' => '<populated from config.ini>',
            'username' => '<populated from config.ini>',
            'password' => '<populated from config.ini>',
            'dbname' => '<populated from config.ini>'
        ),
        'dropboxCreds' => array(
            "key" => "<populated from config.ini>",
            "secret" => "<populated from config.ini>"
        )
    );
    
    private static function loadConfigsFromFile(){
        $configs = parse_ini_file('config.ini',true);
        foreach($configs as $k=>$c){
            self::$_appOptions[$k] = $c;
        }
    }

    public static function getInstance() {
        static $instance = null;
        if (null === $instance) {
            $instance = new static();
            self::loadConfigsFromFile();
        }
        return $instance;
    }

    static public function get($index = null) {
        if (!is_null($index)) {
            if (isset(self::$_appOptions[$index])) {
                return self::$_appOptions[$index];
            } else {
                return null;
            }
        }
        return self::$_appOptions;
    }

    static public function set($option, $value) {
        self::$_appOptions[$option] = $value;
    }

    protected function __construct() {
        
    }

    private function __clone() {
        
    }

    private function __wakeup() {
        
    }

}
