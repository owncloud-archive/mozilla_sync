<?php

/**
* ownCloud
*
* @author Oliver Gasser
* @copyright 2013 Oliver Gasser
*
*/

// Check if this file is called by admin user, otherwise send JSON error 
\OCP\JSON::checkAdminUser();

// Check if valid requesttoken was sent
\OCP\JSON::callCheck();

// Get input and set correct setting
$restrictGroup = filter_var($_POST['restrictgroup'], FILTER_VALIDATE_BOOLEAN);
if ($restrictGroup === true) {
    $group = 'mozilla_sync';
} else {
    $group = null;
}

// Update settings value
\OCA\mozilla_sync\User::setAuthorizedGroup($group);

// Send success message
\OCP\JSON::success();

/* vim: set ts=4 sw=4 tw=80 noet : */

