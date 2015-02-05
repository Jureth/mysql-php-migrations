#!/usr/bin/env php
<?php
/**
 * This file is the main script which should be run on the command line in order to perform database migrations.
 * If you want to use this script like so:  ./migrate.php -- you will need to give it executable permissions (chmod +x migrate.php) and ensure the top line of this script points to the actual location of your PHP binary.
 *
 * @package    mysql_php_migrations
 * @subpackage Globals
 * @license    http://www.opensource.org/licenses/bsd-license.php  The New BSD License
 * @link       http://code.google.com/p/mysql-php-migrations/
 */

// we want to see any errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    throw new Exception('Please run "composer install" first');
}
require __DIR__ . '/vendor/autoload.php';


// fix date issues
if (function_exists('date_default_timezone_set'))
{
    date_default_timezone_set("UTC");
}

/**
 * Define the full path to this file.
 */
define('MPM_PATH', dirname(__FILE__));

/**
 * Version Number - for reference
 */
define('MPM_VERSION', '2.1.8');

if (file_exists(MPM_PATH . '/config/db_config.php'))
{
    /**
     * Include the database connection info.
     */
    $config = require_once(MPM_PATH . '/config/db_config.php');
}else {
    exit('Configuration file is not found' . PHP_EOL);
}

if (!defined('STDIN'))
{
    /**
     * In some cases STDIN built-in can be undefined
     */
    define('STDIN', fopen("php://stdin","r"));
}

// get the proper controller, do the action, and exit the script
array_shift($argv);

\MPM\MPM::init($config);
\MPM\MPM::execute($argv);

exit;
