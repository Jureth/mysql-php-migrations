<?php
/**
 * This file houses the TestData class.
 *
 * This file may be deleted if you do not wish to add test data to your database after a build.
 *
 * @package    mysql_php_migrations
 * @subpackage Classes
 * @license    http://www.opensource.org/licenses/bsd-license.php  The New BSD License
 * @link       http://code.google.com/p/mysql-php-migrations/
 */
use MPM\Classes\Schema;

/**
 * The TestData class is used to add test data after successfully building an initial database structure.
 *
 * @package    mysql_php_migrations
 * @subpackage Classes
 */
class TestData extends Schema
{

	public function build()
 	{
		/* AddController the queries needed to insert test data into the initial build of your database.
		*
		* EX:
		*
		* $this->dbObj->exec("INSERT INTO `testing` (id, username, password) VALUES (1, 'my_username', 'my_password')");
		*/
	}

}
