<?php
/**
 * This file houses the Schema class.
 *
 * @package    mysql_php_migrations
 * @subpackage Classes
 * @license    http://www.opensource.org/licenses/bsd-license.php  The New BSD License
 * @link       http://code.google.com/p/mysql-php-migrations/
 */
namespace MPM\Classes;
use MPM\Helpers\DbHelper;
use MPM\Helpers\ListHelper;
use MPM\MPM;
use PDO;

/**
 * The Schema class is used to build an initial database structure.
 *
 * @package    mysql_php_migrations
 * @subpackage Classes
 */
abstract class Schema
{

    /**
     * Either a PDO or an ExceptionalMysqli object used to talk to the MySQL database.
     *
     * @var PDO|ExceptionalMysqli
     */
    protected $dbObj;

    /**
     * The timestamp of the migration which, when the schema is built, will be considered the current migration.
     *
     * All migrations prior to this timestamp will be ignored when building the database.
     *
     * Timestamp should be in CCYY-MM-DD HH:MM:SS format.
     *
     * @var string
     */
    protected $initialMigrationTimestamp;

    /**
     * Object constructor.
     *
     * @uses MpmDbHelper::getDbObj()
     *
     * @return Schema
     */
    public function __construct()
    {
        $this->dbObj = DbHelper::getDbObj();
        $this->initialMigrationTimestamp = null;
    }

    /**
     * Removes all of the tables in the database.
     *
     * @uses MpmDbHelper::getTables()
     *
     * @return void
     */
    public function destroy()
    {
        $migrations_table = MPM::getTable();
        echo 'Looking for existing tables... ';
        $tables = DbHelper::getTables($this->dbObj);
        $totalTables = count($tables);
        $displayTotal = $totalTables > 1 ? $totalTables - 1 : 0;
        echo 'found ' . $displayTotal . '.';
        if ($totalTables > 1) {
            echo '  Disabling foreign key restrictions...';
            $this->dbObj->exec('SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0');
            $this->dbObj->exec('SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0');
            $this->dbObj->exec("SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL'");
            echo " done.\n";
            echo '  Removing:', "\n";
            foreach ($tables as $table) {
                if ($table != $migrations_table) {
                    echo '        ', $table, "\n";
                    $this->dbObj->exec('DROP TABLE IF EXISTS `' . $table . '`');
                }
            }
            echo '  Re-enabling foreign key restrictions...';
            $this->dbObj->exec('SET SQL_MODE=@OLD_SQL_MODE');
            $this->dbObj->exec('SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS');
            $this->dbObj->exec('SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS');
            echo " done.\n";
        } else {
            echo '  No tables need to be removed.', "\n";
        }
    }

    /**
     * Clears the migrations table and then rebuilds it.
     *
     * @uses MpmListHelper::mergeFilesWithDb()
     * @uses MpmDbHelper::doSingleRowSelect()
     *
     * @return void
     */
    public function reloadMigrations()
    {
        $migrations_table = MPM::getTable();
        echo 'Clearing out existing migration data... ';
        $this->dbObj->exec('TRUNCATE TABLE `' . $migrations_table . '`');
        echo 'done.', "\n\n", 'Rebuilding migration data... ';
        ListHelper::mergeFilesWithDb();
        echo 'done.', "\n";
        if ($this->initialMigrationTimestamp != null) {
            echo "\n", 'Updating initial migration timestamp to ', $this->initialMigrationTimestamp, '... ';
            $result = DbHelper::doSingleRowSelect('SELECT COUNT(*) AS total FROM ' . $migrations_table . ' WHERE timestamp = "' . $this->initialMigrationTimestamp . '"', $this->dbObj);
            if ($result->total == 1) {
                $this->dbObj->exec('UPDATE `' . $migrations_table . '` SET `is_current` = 0');
                $this->dbObj->exec('UPDATE `' . $migrations_table . '` SET `is_current` = 1 WHERE `timestamp` = "' . $this->initialMigrationTimestamp . '"');
                $this->dbObj->exec('UPDATE `' . $migrations_table . '` SET `active` = 1 WHERE `timestamp` <= "' . $this->initialMigrationTimestamp . '"');
            }
            echo 'done.', "\n";
        }
    }

    /**
     * Used to build the schema.  All SQL statements needed to create the initial database structure should be run here.
     *
     * @return void
     */
    abstract public function build();

}
