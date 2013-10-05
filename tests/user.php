<?php

OC_App::loadApp('mozilla_sync');
class Test_User extends PHPUnit_Framework_TestCase {
  private $user;

	function setUp() {
	  $this->user = uniqid('test_');
	}

	function test() {
	// TODO
	}
}

/* vim: set ts=4 sw=4 tw=80 noet : */
