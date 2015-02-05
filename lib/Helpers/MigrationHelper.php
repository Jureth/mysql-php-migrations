<?php
/**
 * This file houses the MigrationHelper class.
 *
 * @package    mysql_php_migrations
 * @subpackage Controllers
 * @license    http://www.opensource.org/licenses/bsd-license.php  The New BSD License
 * @link       http://code.google.com/p/mysql-php-migrations/
 */
namespace MPM\Helpers;
use MPM\Classes\CommandLineWriter;
use MPM\Classes\Migration;
use MPM\MPM;

/**
 * The MigrationHelper contains a number of static functions which are used during the migration process.
 *
 * @package    mysql_php_migrations
 * @subpackage Controllers
 */
class MigrationHelper
{

    /**
     * Sets the current active migration.
     *
     * @uses MpmDbHelper::getDbObj()
     *
     * @param int $id the ID of the migration to set as the current one
     *
     * @return void
     */
    static public function setCurrentMigration($id)
    {
        $migrations_table = MPM::getTable();
        $sql1 = "UPDATE `{$migrations_table}` SET `is_current` = '0'";
        $sql2 = "UPDATE `{$migrations_table}` SET `is_current` = '1' WHERE `id` = {$id}";
        $obj = DbHelper::getDbObj();
        $obj->beginTransaction();
        try {
            $obj->exec('SET SQL_SAFE_UPDATES = 0');
            $obj->exec($sql1);
            $obj->exec($sql2);
            $obj->exec('SET SQL_SAFE_UPDATES = 1');
        } catch (Exception $e) {
            $obj->rollback();
            echo "\n\tQuery failed!";
            echo "\n\t--- " . $e->getMessage();
            exit;
        }
        $obj->commit();
    }


    /**
     * Performs a single migration.
     *
     * @uses MpmStringHelper::getFilenameFromTimestamp()
     * @uses MpmDbHelper::getPdoObj()
     * @uses MpmDbHelper::getMysqliObj()
     * @uses MpmCommandLineWriter::getInstance()
     * @uses MpmCommandLineWriter::writeLine()
     * @uses MPM_DB_PATH
     *
     * @param object $obj a simple object with migration information (from a migration list)
     * @param int &$total_migrations_run a running total of migrations run
     * @param bool $forced if true, exceptions will not cause the script to exit
     *
     * @return void
     */
    static public function runMigration(&$obj, $method = 'up', $forced = false)
    {
        $migrations_table = MPM::getTable();
        $filename = StringHelper::getFilenameFromTimestamp($obj->timestamp);
        $classname = 'Migration_' . str_replace('.php', '', $filename);

        // make sure the file exists; if it doesn't, skip it but display a message
        if (!file_exists(MPM::getMigrationsPath() . DIRECTORY_SEPARATOR . $filename)) {
            echo "\n\tMigration " . $obj->timestamp . ' (ID ' . $obj->id . ') skipped - file missing.';
            return;
        }

        // file exists -- run the migration
        echo "\n\tPerforming " . strtoupper($method) . " migration " . $obj->timestamp . ' (ID ' . $obj->id . ')... ';
        require_once(MPM::getMigrationsPath() . DIRECTORY_SEPARATOR . $filename);
        $migration = new $classname();
        if ($migration instanceof Migration) // need PDO object
        {
            $dbObj = DbHelper::getPdoObj();
        } else {
            $dbObj = DbHelper::getMysqliObj();
        }
        $dbObj->beginTransaction();
        if ($method == 'down') {
            $active = 0;
        } else {
            $active = 1;
        }
        try {
            $migration->$method($dbObj);
            $sql = "UPDATE `{$migrations_table}` SET `active` = '$active' WHERE `id` = {$obj->id}";
            $dbObj->exec($sql);
        } catch (Exception $e) {
            $dbObj->rollback();
            echo "failed!";
            echo "\n";
            $clw = CommandLineWriter::getInstance();
            $clw->writeLine($e->getMessage(), 12);
            if (!$forced) {
                echo "\n\n";
                exit;
            } else {
                return;
            }
        }
        $dbObj->commit();
        echo "done.";
    }

    /**
     * Returns the timestamp of the migration currently rolled to.
     *
     * @uses MpmDbHelper::getDbObj()
     * @uses MpmDbHelper::getMethod()
     * @uses MPM::METHOD_PDO
     * @uses MPM::METHOD_MYSQLi
     *
     * @return string
     */
    static public function getCurrentMigrationTimestamp()
    {
        $migrations_table = MPM::getTable();

        // Resolution to Issue #1 - PDO::rowCount is not reliable
        $sql1 = "SELECT COUNT(*) as total FROM `{$migrations_table}` WHERE `is_current` = 1";
        $sql2 = "SELECT `timestamp` FROM `{$migrations_table}` WHERE `is_current` = 1";
        $dbObj = DbHelper::getDbObj();
        switch (DbHelper::getMethod()) {
            case MPM::METHOD_PDO:
                $stmt = $dbObj->query($sql1);
                if ($stmt->fetchColumn() == 0) {
                    return false;
                }
                unset($stmt);
                $stmt = $dbObj->query($sql2);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $latest = $row['timestamp'];
                break;
            case MPM::METHOD_MYSQLi:
                $result = $dbObj->query($sql1);
                $row = $result->fetch_object();
                if ($row->total == 0) {
                    return false;
                }
                $result->close();
                unset($result);
                $result = $dbObj->query($sql2);
                $row = $result->fetch_object();
                $latest = $row->timestamp;
                break;
        }
        return $latest;
    }

    /**
     * Returns an array of migrations which need to be run (in order).
     *
     * @uses MpmMigrationHelper::getTimestampFromId()
     * @uses MpmDbHelper::getMethod()
     * @uses MpmDbHelper::getPdoObj()
     * @uses MpmDbHelper::getMysqliObj()
     * @uses MPM::METHOD_MYSQLi
     * @uses MPM::METHOD_PDO
     *
     * @param int $toId the ID of the migration to stop on
     * @param string $direction the direction of the migration; should be 'up' or 'down'
     *
     * @return array
     */
    static public function getListOfMigrations($toId, $direction = 'up')
    {
        $migrations_table = MPM::getTable();
        $list = array();
        $timestamp = MigrationHelper::getTimestampFromId($toId);
        if ($direction == 'up') {
            $sql = "SELECT `id`, `timestamp` FROM `{$migrations_table}` WHERE `active` = 0 AND `timestamp` <= '$timestamp' ORDER BY `timestamp`";
        } else {
            $sql = "SELECT `id`, `timestamp` FROM `{$migrations_table}` WHERE `active` = 1 AND `timestamp` > '$timestamp' ORDER BY `timestamp` DESC";
        }
        switch (DbHelper::getMethod()) {
            case MPM::METHOD_PDO:
                try {
                    $pdo = DbHelper::getPdoObj();
                    $stmt = $pdo->query($sql);
                    while ($obj = $stmt->fetch(\PDO::FETCH_OBJ)) {
                        $list[$obj->id] = $obj;
                    }
                } catch (Exception $e) {
                    echo "\n\nError: " . $e->getMessage() . "\n\n";
                    exit;
                }
                break;
            case MPM::METHOD_MYSQLi:
                try {
                    $mysqli = DbHelper::getMysqliObj();
                    $results = $mysqli->query($sql);
                    while ($row = $results->fetch_object()) {
                        $list[$row->id] = $row;
                    }
                } catch (Exception $e) {
                    echo "\n\nError: " . $e->getMessage() . "\n\n";
                    exit;
                }
                break;

        }
        return $list;
    }

    /**
     * Returns a timestamp when given a migration ID number.
     *
     * @uses MpmDbHelper::getMethod()
     * @uses MpmDbHelper::getPdoObj()
     * @uses MpmDbHelper::getMysqliObj()
     * @uses MPM::METHOD_MYSQLi
     * @uses MPM::METHOD_PDO
     *
     * @param int $id the ID number of the migration
     *
     * @return string
     */
    static public function getTimestampFromId($id)
    {
        $migrations_table = MPM::getTable();
        try {
            switch (DbHelper::getMethod()) {
                case MPM::METHOD_PDO:
                    // Resolution to Issue #1 - PDO::rowCount is not reliable
                    $pdo = DbHelper::getPdoObj();
                    $sql = "SELECT COUNT(*) FROM `{$migrations_table}` WHERE `id` = '$id'";
                    $stmt = $pdo->query($sql);
                    if ($stmt->fetchColumn() == 1) {
                        unset($stmt);
                        $sql = "SELECT `timestamp` FROM `{$migrations_table}` WHERE `id` = '$id'";
                        $stmt = $pdo->query($sql);
                        $result = $stmt->fetch(\PDO::FETCH_OBJ);
                        $timestamp = $result->timestamp;
                    } else {
                        $timestamp = false;
                    }
                    break;
                case MPM::METHOD_MYSQLi:
                    $mysqli = DbHelper::getMysqliObj();
                    $sql = "SELECT COUNT(*) as total FROM `{$migrations_table}` WHERE `id` = '$id'";
                    $stmt = $mysqli->query($sql);
                    $row = $stmt->fetch_object();
                    if ($row->total == 1) {
                        $stmt->close();
                        unset($stmt);
                        $sql = "SELECT `timestamp` FROM `{$migrations_table}` WHERE `id` = '$id'";
                        $stmt = $mysqli->query($sql);
                        $result = $stmt->fetch_object();
                        $timestamp = $result->timestamp;
                        $stmt->close();
                        $mysqli->close();
                    } else {
                        $timestamp = false;
                    }
                    break;
            }
        } catch (Exception $e) {
            echo "\n\nERROR: " . $e->getMessage() . "\n\n";
            exit;
        }
        return $timestamp;
    }

    /**
     * Returns the number of the migration currently rolled to.
     *
     * @uses MpmDbHelper::getMethod()
     * @uses MpmDbHelper::getDbObj()
     * @uses MPM::METHOD_MYSQLi
     * @uses MPM::METHOD_PDO
     *
     * @return string
     */
    static public function getCurrentMigrationNumber()
    {
        $migrations_table = MPM::getTable();
        try {
            switch (DbHelper::getMethod()) {
                case MPM::METHOD_PDO:
                    $pdo = DbHelper::getDbObj();
                    // Resolution to Issue #1 - PDO::rowCount is not reliable
                    $sql = "SELECT COUNT(*) FROM `{$migrations_table}` WHERE `is_current` = 1";
                    $stmt = $pdo->query($sql);
                    if ($stmt->fetchColumn() == 0) {
                        return false;
                    }
                    $sql = "SELECT `id` FROM `{$migrations_table}` WHERE `is_current` = 1";
                    unset($stmt);
                    $stmt = $pdo->query($sql);
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    $latest = $row['id'];
                    break;
                case MPM::METHOD_MYSQLi:
                    $mysqli = DbHelper::getDbObj();
                    $sql = "SELECT COUNT(*) as total FROM `{$migrations_table}` WHERE `is_current` = 1";
                    $stmt = $mysqli->query($sql);
                    $row = $stmt->fetch_object();
                    if ($row->total == 0) {
                        return false;
                    }
                    $stmt->close();
                    unset($stmt);
                    $sql = "SELECT `id` FROM `{$migrations_table}` WHERE `is_current` = 1";
                    $stmt = $mysqli->query($sql);
                    $row = $stmt->fetch_object();
                    $latest = $row->id;
                    $stmt->close();
                    $mysqli->close();
                    break;
            }
        } catch (Exception $e) {
            echo "\n\nERROR: " . $e->getMessage() . "\n\n";
            exit;
        }
        return $latest;
    }

    /**
     * Returns the total number of migrations.
     *
     * @uses MpmDbHelper::getMethod()
     * @uses MpmDbHelper::getDbObj()
     * @uses MPM::METHOD_MYSQLi
     * @uses MPM::METHOD_PDO
     *
     * @return int
     */
    static public function getMigrationCount()
    {
        $migrations_table = MPM::getTable();
        try {
            switch (DbHelper::getMethod()) {
                case MPM::METHOD_PDO:
                    $pdo = DbHelper::getDbObj();
                    // Resolution to Issue #1 - PDO::rowCount is not reliable
                    $sql = "SELECT COUNT(id) FROM `{$migrations_table}`";
                    $stmt = $pdo->query($sql);
                    $count = $stmt->fetchColumn();
                    break;
                case MPM::METHOD_MYSQLi:
                    $mysqli = DbHelper::getDbObj();
                    $sql = "SELECT COUNT(id) AS total FROM `{$migrations_table}`";
                    $stmt = $mysqli->query($sql);
                    $row = $stmt->fetch_object();
                    $count = $row->total;
                    break;
            }
        } catch (Exception $e) {
            echo "\n\nERROR: " . $e->getMessage() . "\n\n";
            exit;
        }
        return $count;
    }

    /**
     * Returns the ID of the latest migration.
     *
     * @uses MpmDbHelper::getMethod()
     * @uses MpmDbHelper::getDbObj()
     * @uses MPM::METHOD_MYSQLi
     * @uses MPM::METHOD_PDO
     *
     * @return int
     */
    static public function getLatestMigration()
    {
        $migrations_table = MPM::getTable();
        $sql = "SELECT `id` FROM `{$migrations_table}` ORDER BY `timestamp` DESC LIMIT 0,1";
        try {
            switch (DbHelper::getMethod()) {
                case MPM::METHOD_PDO:
                    $pdo = DbHelper::getDbObj();
                    $stmt = $pdo->query($sql);
                    $result = $stmt->fetch(\PDO::FETCH_OBJ);
                    $to_id = $result->id;
                    break;
                case MPM::METHOD_MYSQLi:
                    $mysqli = DbHelper::getDbObj();
                    $stmt = $mysqli->query($sql);
                    $result = $stmt->fetch_object();
                    $to_id = $result->id;
                    break;
            }
        } catch (Exception $e) {
            echo "\n\nERROR: " . $e->getMessage() . "\n\n";
            exit;
        }
        return $to_id;
    }

    /**
     * Checks to see if a migration with the given ID actually exists.
     *
     * @uses MpmDbHelper::getMethod()
     * @uses MpmDbHelper::getDbObj()
     * @uses MPM::METHOD_MYSQLi
     * @uses MPM::METHOD_PDO
     *
     * @param int $id the ID of the migration
     *
     * @return int
     */
    static public function doesMigrationExist($id)
    {
        $migrations_table = MPM::getTable();
        $sql = "SELECT COUNT(*) as total FROM `{$migrations_table}` WHERE `id` = '$id'";
        $return = false;
        try {
            switch (DbHelper::getMethod()) {
                case MPM::METHOD_PDO:
                    $pdo = DbHelper::getDbObj();
                    $stmt = $pdo->query($sql);
                    $result = $stmt->fetch(\PDO::FETCH_OBJ);
                    if ($result->total > 0) {
                        $return = true;
                    }
                    break;
                case MPM::METHOD_MYSQLi:
                    $mysqli = DbHelper::getDbObj();
                    $stmt = $mysqli->query($sql);
                    $result = $stmt->fetch_object();
                    if ($result->total > 0) {
                        $return = true;
                    }
                    break;
            }
        } catch (Exception $e) {
            echo "\n\nERROR: " . $e->getMessage() . "\n\n";
            exit;
        }
        return $return;
    }

    /**
     * Returns a migration object; this object contains all data stored in the DB for the particular migration ID.
     *
     * @uses MpmDbHelper::getMethod()
     * @uses MpmDbHelper::getDbObj()
     * @uses MPM::METHOD_MYSQLi
     * @uses MPM::METHOD_PDO
     *
     * @param int $id the ID of the migration
     *
     * @return object
     */
    static public function getMigrationObject($id)
    {
        $migrations_table = MPM::getTable();
        $sql = "SELECT * FROM `{$migrations_table}` WHERE `id` = '$id'";
        $obj = null;
        try {
            switch (DbHelper::getMethod()) {
                case MPM::METHOD_PDO:
                    $pdo = DbHelper::getDbObj();
                    $stmt = $pdo->query($sql);
                    $obj = $stmt->fetch(\PDO::FETCH_OBJ);
                    break;
                case MPM::METHOD_MYSQLi:
                    $mysqli = DbHelper::getDbObj();
                    $stmt = $mysqli->query($sql);
                    $obj = $stmt->fetch_object();
                    break;
            }
        } catch (Exception $e) {
            echo "\n\nERROR: " . $e->getMessage() . "\n\n";
            exit;
        }
        return $obj;
    }
}
