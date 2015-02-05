<?php

namespace MPM\Controllers;


use MPM\Helpers\DbHelper;
use MPM\Helpers\ListHelper;

abstract class ActionController extends BaseController {

  public function __construct($command = 'help', $arguments = array()) {
    parent::__construct($command, $arguments);
    DbHelper::test();
    ListHelper::mergeFilesWithDb();
  }

}
