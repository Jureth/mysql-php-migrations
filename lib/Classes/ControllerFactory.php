<?php
/**
 * This file houses the ControllerFactory class.
 *
 * @package    mysql_php_migrations
 * @subpackage Classes
 * @license    http://www.opensource.org/licenses/bsd-license.php  The New BSD License
 * @link       http://code.google.com/p/mysql-php-migrations/
 */

namespace MPM\Classes;
use MPM\Exceptions\ClassUndefinedException;
use MPM\Controllers\BaseController;

/**
 * The ControllerFactory reads the command line arguments, determines which controller is needed, and returns that controlller object.
 *
 * @package    mysql_php_migrations
 * @subpackage Classes
 */
class ControllerFactory
{

    /**
     * Given an array of command line arguments ($argv), determines the controller needed and returns that object.
     *
     * @param $argv
     * @throws \MPM\Exceptions\ClassUndefinedException
     * @return BaseController
     */
    static public function getInstance($argv)
    {
        $controller_name = array_shift($argv);
        if ($controller_name == null) {
            $controller_name = 'help';
        }
        $class_name = self::BuildClassName($controller_name);
        if (!class_exists($class_name)) {
            throw new ClassUndefinedException('Class ' . $class_name . ' not found');
        }
        return new $class_name($controller_name, $argv);
    }

    public static function BuildClassName($name) {
        return '\MPM\Controllers\\' . ucwords($name) . 'Controller';
    }

}
