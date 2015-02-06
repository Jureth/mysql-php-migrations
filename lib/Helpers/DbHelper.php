<?php
/**
 * This file houses the DbHelper class.
 *
 * @package mysql_php_migrations
 * @subpackage Helpers
 * @license    http://www.opensource.org/licenses/bsd-license.php  The New BSD License
 * @link       http://code.google.com/p/mysql-php-migrations/
 */
namespace MPM\Helpers;
use Exception;
use MPM\Classes\CommandLineWriter;
use MPM\Classes\ExceptionalMysqli;
use MPM\Exceptions\DatabaseConnectionException;
use MPM\MPM;
use PDO;

/**
 * The DbHelper class is used to fetch database objects (PDO or Mysqli right now) and perform basic database actions.
 *
 * @package mysql_php_migrations
 * @subpackage Helpers
 */
class DbHelper
{

    /**
     * Returns the correct database object based on the database configuration file.
     *
     * @throws Exception if database configuration file is missing or method is incorrectly defined
     *
     * @uses MpmDbHelper::getPdoObj()
     * @uses MpmDbHelper::getMysqliObj()
     * @uses MpmDbHelper::getMethod()
     * @uses MPM_METHOD_PDO
     * @uses MPM::METHOD_MYSQLi
     *
     * @return object
     */
    static public function getDbObj()
    {
        switch (DbHelper::getMethod()) {
            case MPM::METHOD_PDO:
                return DbHelper::getPdoObj();
            case MPM::METHOD_MYSQLi:
                return DbHelper::getMysqliObj();
            default:
                throw new Exception('Unknown database connection method defined in database configuration.');
        }
    }

    /**
     * Returns a PDO object with connection in place.
     *
     * @throws DatabaseConnectionException if unable to connect to the database
     *
     * @return PDO
     */
    static public function getPdoObj()
    {
        $pdo_settings = array
        (
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
        );
        return new PDO(MPM::getDSN(), MPM::getUser(), MPM::getPass(), $pdo_settings);
    }

    /**
     * Returns an ExceptionalMysqli object with connection in place.
     *
     * @throws DatabaseConnectionException if unable to connect to the database
     *
     * @return \MPM\Classes\ExceptionalMysqli
     */
    static public function getMysqliObj()
    {
        return new ExceptionalMysqli(MPM::getHost(), MPM::getUser(), MPM::getPass(), MPM::getDatabaseName(), MPM::getPort());
    }

    /**
     * Returns the correct database connection method as set in the database configuration file.
     *
     * @return int
     */
    static public function getMethod()
    {
        return MPM::getMethod();
    }

    /**
     * Performs a query; $sql should be a SELECT query that returns exactly 1 row of data; returns an object that contains the row
     *
     * @uses MpmDbHelper::getDbObj()
     * @uses MpmDbHelper::getMethod()
     * @uses MPM_METHOD_PDO
     * @uses MPM::METHOD_MYSQLi
     *
     * @param string $sql a SELECT query that returns exactly 1 row of data
     * @param object $db a PDO or ExceptionalMysqli object that can be used to run the query
     *
     * @return \stdClass
     */
    static public function doSingleRowSelect($sql, &$db = null)
    {
        try {
            if ($db == null) {
                $db = DbHelper::getDbObj();
            }
            switch (DbHelper::getMethod()) {
                case MPM::METHOD_PDO:
                    /** @var PDO $db */
                    $stmt = $db->query($sql);
                    $obj = $stmt->fetch(PDO::FETCH_OBJ);
                    return $obj;
                case MPM::METHOD_MYSQLi:
                    /** @var \mysqli $db */
                    $stmt = $db->query($sql);
                    $obj = $stmt->fetch_object();
                    return $obj;
                default:
                    throw new Exception('Unknown method defined in database configuration.');
            }
        } catch (Exception $e) {
            echo "\n\nError: ", $e->getMessage(), "\n\n";
            exit;
        }
    }

    /**
     * Performs a SELECT query
     *
     * @uses MpmDbHelper::getDbObj()
     * @uses MpmDbHelper::getMethod()
     * @uses MPM_METHOD_PDO
     * @uses MPM::METHOD_MYSQLi
     *
     * @param string $sql a SELECT query
     *
     * @return array
     */
    static public function doMultiRowSelect($sql)
    {
        try {
            $db = DbHelper::getDbObj();
            $results = array();
            switch (DbHelper::getMethod()) {
                case MPM::METHOD_PDO:
                    $stmt = $db->query($sql);
                    while ($obj = $stmt->fetch(PDO::FETCH_OBJ)) {
                        $results[] = $obj;
                    }
                    return $results;
                case MPM::METHOD_MYSQLi:
                    $stmt = $db->query($sql);
                    while ($obj = $stmt->fetch_object()) {
                        $results[] = $obj;
                    }
                    return $results;
                default:
                    throw new Exception('Unknown method defined in database configuration.');
            }
        } catch (Exception $e) {
            echo "\n\nError: ", $e->getMessage(), "\n\n";
            exit;
        }
    }

    /**
     * Checks to make sure everything is in place to be able to use the migrations tool.
     *
     * @return void
     */
    static public function test()
    {
        $problems = array();
        switch (DbHelper::getMethod()) {
            case MPM::METHOD_PDO:
                if (!class_exists('PDO')) {
                    $problems[] = 'It does not appear that the PDO extension is installed.';
                }
                $drivers = PDO::getAvailableDrivers();
                if (!in_array('mysql', $drivers)) {
                    $problems[] = 'It appears that the mysql driver for PDO is not installed.';
                }
                if (count($problems) == 0) {
                    try {
                        $pdo = DbHelper::getPdoObj();
                    } catch (Exception $e) {
                        $problems[] = 'Unable to connect to the database: ' . $e->getMessage();
                    }
                }
                break;
            case MPM::METHOD_MYSQLi:
                if (!class_exists('mysqli')) {
                    $problems[] = "It does not appear that the mysqli extension is installed.";
                }
                if (count($problems) == 0) {
                    try {
                        $mysqli = DbHelper::getMysqliObj();
                    } catch (Exception $e) {
                        $problems[] = "Unable to connect to the database: " . $e->getMessage();
                    }
                }
                break;
        }
        if (!DbHelper::checkForDbTable()) {
            $problems[] = 'Migrations table not found in your database.  Re-run the init command.';
        }
        if (count($problems) > 0) {
            $obj = CommandLineWriter::getInstance();
            $obj->addText("It appears there are some problems:");
            $obj->addText("\n");
            foreach ($problems as $problem) {
                $obj->addText($problem, 4);
                $obj->addText("\n");
            }
            $obj->write();
            exit;
        }
    }

    /**
     * Checks whether or not the migrations database table exists.
     *
     * @uses MpmDbHelper::getDbObj()
     * @uses MpmDbHelper::getMethod()
     * @uses MPM_METHOD_PDO
     * @uses MPM::METHOD_MYSQLi
     *
     * @return bool
     */
    static public function checkForDbTable()
    {
        $migrations_table = MPM::getTable();
        $tables = DbHelper::getTables();
        if (count($tables) == 0 || !in_array($migrations_table, $tables)) {
            return false;
        }
        return true;
    }

    /**
     * Returns an array of all the tables in the database.
     *
     * @uses MpmDbHelper::getDbObj()
     * @uses MpmDbHelper::getMethod()
     *
     * @return array
     */
    static public function getTables(&$dbObj = null)
    {
        if ($dbObj == null) {
            $dbObj = DbHelper::getDbObj();
        }
        $sql = "SHOW TABLES";
        $tables = array();
        switch (DbHelper::getMethod()) {
            case MPM::METHOD_PDO:
                try {
                    foreach ($dbObj->query($sql) as $row) {
                        $tables[] = $row[0];
                    }
                } catch (Exception $e) {
                }
                break;
            case MPM::METHOD_MYSQLi:
                try {
                    $result = $dbObj->query($sql);
                    while ($row = $result->fetch_array()) {
                        $tables[] = $row[0];
                    }
                } catch (Exception $e) {
                }
                break;
        }
        return $tables;
    }

    public static function createMigrationTable(){
        $migrations_table = MPM::getTable();
        $sql1 = "CREATE TABLE IF NOT EXISTS {$migrations_table} ( id INT(11) NOT NULL AUTO_INCREMENT, timestamp DATETIME NOT NULL, active TINYINT(1) NOT NULL DEFAULT 0, `is_current` TINYINT(1) NOT NULL DEFAULT 0, PRIMARY KEY ( `id` ) ) ENGINE=InnoDB";
        $sql2 = "CREATE UNIQUE INDEX TIMESTAMP_INDEX ON {$migrations_table} ( timestamp )";

        if (self::getMethod() == MPM::METHOD_PDO) {
            $pdo = self::getDbObj();
            $pdo->beginTransaction();
            try {
                $pdo->exec($sql1);
                $pdo->exec($sql2);
            } catch (Exception $e) {
                $pdo->rollback();
                return $e->getMessage();
            }
            $pdo->commit();
        } else {
            $mysqli = self::getDbObj();
            $mysqli->query($sql1);
            if ($mysqli->errno) {
                return $mysqli->error;
            }
            $mysqli->query($sql2);
            if ($mysqli->errno) {
                return $mysqli->error;
            }
        }
        return null;
    }
}
