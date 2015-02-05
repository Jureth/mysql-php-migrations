<?php

namespace MPM;


use MPM\Classes\ControllerFactory;

class MPM {
    const METHOD_PDO = 1;
    const METHOD_MYSQLi = 2;

    protected static $config = array();

    public static function init($config = array()){
        $config = $config + [
                'method' => self::METHOD_PDO,
                'migrations_table' => 'mpm_migrations',
                'host' => 'localhost',
                'port' => '3306',
                'migrations_path' => '',
            ];
        if (self::validate($config)) {
            self::$config = $config;
        }
    }

    protected static function validate($config){
        return !empty($config['database'])
            && !empty($config['host'])
            && !empty($config['user'])
            && !empty($config['password'])
            && !empty($config['migrations_table'])
            && !empty($config['migrations_path'])
            && in_array($config['method'], [ self::METHOD_PDO, self::METHOD_MYSQLi]);
    }

    public static function getDSN() {
        if (!isset(self::$config['dsn'])) {
            self::$config['dsn'] = sprintf('mysql:host=%s;port=%s;dbname=%s', self::$config['host'], self::$config['port'], self::$config['database']);
        }
        return self::$config['dsn'];
    }

    public static function getUser() {
        return self::$config['user'];
    }

    public static function getPass() {
        return self::$config['password'];
    }

    public static function getMigrationsPath(){
        return self::$config['migrations_path'];
    }

    public static function getMethod() {
        return self::$config['method'];
    }

    public static function getTable() {
        return self::$config['migrations_table'];
    }

    public static function getHost(){
        return self::$config['host'];
    }

    public static function getPort(){
        return self::$config['port'];
    }

    public static function  getDatabaseName(){
        return self::$config['database'];
    }

    public static function getBasePath(){
        return __DIR__ . '/../';
    }

    public static function execute($args){
        $obj = ControllerFactory::getInstance($args);
        $obj->doAction();
    }
}
