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

// Load translations
$l = OC_L10N::get('core');

// Get inputs and set correct settings
$quota = filter_var($_POST['quota'], FILTER_VALIDATE_INT);
if ($quota === false) {
    // Send error message
    \OCP\JSON::error(array( "data" => array( "message" => $l->t("Invalid input") )));
} else {
    // Update settings values
    \OCA\mozilla_sync\User::setQuota($quota);

    // Send success message
    \OCP\JSON::success(array( "data" => array( "message" => $l->t("Quota saved") )));
}

/* vim: set ts=4 sw=4 tw=80 noet : */
