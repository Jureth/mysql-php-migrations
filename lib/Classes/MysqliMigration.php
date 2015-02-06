<?php
/**
 * This file houses the MysqliMigration class.
 *
 * @package    mysql_php_migrations
 * @subpackage Controllers
 * @license    http://www.opensource.org/licenses/bsd-license.php  The New BSD License
 * @link       http://code.google.com/p/mysql-php-migrations/
 */
namespace MPM\Classes;

/**
 * The MysqliMigration is an abstract template class used as the parent to all migration classes which use mysqli.
 *
 * @package    mysql_php_migrations
 * @subpackage Controllers
 */
abstract class MysqliMigration
{
    /**
     * Migrates the database up.
     *
     * @param ExceptionalMysqli $mysqli an ExceptionalMysqli object
     *
     * @return void
     */
    abstract public function up(ExceptionalMysqli &$mysqli);

    /**
     * Migrates down (reverses changes made by the up method).
     *
     * @param ExceptionalMysqli $mysqli an ExceptionalMysqli object
     *
     * @return void
     */
    abstract public function down(ExceptionalMysqli &$mysqli);
}