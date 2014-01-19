<?php

/**
* ownCloud
*
* @author Oliver Gasser
* @copyright 2013 Oliver Gasser
*
*/

// Check if user is logged in
\OCP\JSON::checkLoggedIn();

// Check if valid requesttoken was sent
\OCP\JSON::callCheck();

// Load translations
$l = OC_L10N::get('mozilla_sync');

// Get inputs and set correct settings
$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
if ($email === false) {
    // Send error message
    \OCP\JSON::error(array( "data" => array( "message" => $l->t("Invalid input") )));
} else {
    // Update settings values
    \OCA\mozilla_sync\User::setEmail($email);

    // Send success message
    \OCP\JSON::success(array( "data" => array( "message" => $l->t("Sync email saved") )));
}

/* vim: set ts=4 sw=4 tw=80 noet : */
