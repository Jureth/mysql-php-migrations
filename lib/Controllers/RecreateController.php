<?php
/**
 * @file
 */

namespace MPM\Controllers;


use MPM\Classes\CommandLineWriter;
use MPM\Helpers\DbHelper;

class RecreateController extends BaseController {


  /**
   * Determines what action should be performed and takes that action.
   *
   * @return void
   */
  public function doAction() {
    DbHelper::createMigrationTable();
  }

  /**
   * Displays the help page for this controller.
   *
   * @return void
   */
  public function displayHelp() {
    $obj = CommandLineWriter::getInstance();
    $obj->addText('./migrate.php recreate');
    $obj->addText('Creates migration table');
  }
}
