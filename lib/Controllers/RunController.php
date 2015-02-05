<?php
/**
 * This file houses the RunController class.
 *
 * @package    mysql_php_migrations
 * @subpackage Controllers
 * @license    http://www.opensource.org/licenses/bsd-license.php  The New BSD License
 * @link       http://code.google.com/p/mysql-php-migrations/
 */
namespace MPM\Controllers;
use MPM\Classes\CommandLineWriter;
use MPM\Helpers\DbHelper;
use MPM\Helpers\MigrationHelper;

/**
 * The RunController is used to run a single migration.
 *
 * @package    mysql_php_migrations
 * @subpackage Controllers
 */
class RunController extends ActionController
{

    /**
     * Determines what action should be performed and takes that action.
     *
     * @uses MpmRunController::displayHelp()
     * @uses MpmCommandLineWriter::getInstance()
     * @uses MpmCommandLineWriter::addText()
     * @uses MpmCommandLineWriter::write()
     * @uses MpmCommandLineWriter::writeHeader()
     * @uses MpmCommandLineWriter::writeFooter()
     * @uses MpmMigrationHelper::doesMigrationExist()
     * @uses MpmMigrationHelper::getMigrationObject()
     * @uses MpmMigrationHelper::runMigration()
     *
     * @return void
     */
    public function doAction()
    {
        // make sure system is init'ed
        DbHelper::test();

        if (count($this->arguments) != 2) {
            $obj = CommandLineWriter::getInstance();
            $obj->addText('ERROR: You must provide two arguments with this command.');
            $obj->addText(' ');
            $this->displayHelp();
            return;
        }

        // are we running the up or the down?
        $type = strtolower($this->arguments[0]);

        // what number do we want to run?
        $num = $this->arguments[1];

        if (!is_numeric($num)) {
            $obj = CommandLineWriter::getInstance();
            $obj->addText('ERROR: Migration number must be numeric.');
            $obj->addText(' ');
            $this->displayHelp();
            return;
        }

        if ($type != 'up' && $type != 'down') {
            $obj = CommandLineWriter::getInstance();
            $obj->addText('ERROR: Method must be either up or down.');
            $obj->addText(' ');
            $this->displayHelp();
            return;
        }

        // does this migration number exist?
        if (!MigrationHelper::doesMigrationExist($num)) {
            $obj = CommandLineWriter::getInstance();
            $obj->addText('ERROR: Migration ' . $num . ' does not exist.');
            $obj->write();
            return;
        }

        $row = MigrationHelper::getMigrationObject($num);
        $obj = CommandLineWriter::getInstance();
        $obj->writeHeader();
        MigrationHelper::runMigration($row, $type);
        echo "\n";
        $obj->writeFooter();

    }

    /**
     * Displays the help page for this controller.
     *
     * @uses MpmCommandLineWriter::getInstance()
     * @uses MpmCommandLineWriter::addText()
     * @uses MpmCommandLineWriter::write()
     *
     * @return void
     */
    public function displayHelp()
    {
        $obj = CommandLineWriter::getInstance();
        $obj->addText('./migrate.php run [method] [migration #]');
        $obj->addText(' ');
        $obj->addText('This command is used to run a single migration.');
        $obj->addText(' ');
        $obj->addText('You must specify a method to run (either up or down) and a migration # (as provided by the list command)');
        $obj->addText(' ');
        $obj->addText('Valid Examples:');
        $obj->addText('./migrate.php run up 13', 4);
        $obj->addText('./migrate.php run down 12', 4);
        $obj->write();
    }

}
