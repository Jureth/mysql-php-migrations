<?php
/**
 * This file houses the MalformedQueryException class.
 *
 * @package    mysql_php_migrations
 * @subpackage Exceptions
 * @license    http://www.opensource.org/licenses/bsd-license.php  The New BSD License
 * @link       http://code.google.com/p/mysql-php-migrations/
 */

namespace MPM\Exceptions;
use Exception;

/**
 * MalformedQueryException should be thrown if MySQL rejects a query.
 *
 * @package    mysql_php_migrations
 * @subpackage Exceptions
 */
class MalformedQueryException extends Exception
{

}
