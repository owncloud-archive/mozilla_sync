<?php

// Check if this file is called by admin user, otherwise send JSON error
\OCP\JSON::checkAdminUser();

// Check if valid requesttoken was sent
\OCP\JSON::callCheck();

// Load translations
$l = OC_L10N::get('mozilla_sync');

// Get inputs and set correct settings
$autocreate = filter_var($_POST['autocreate'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
if ($autocreate === null) {
    // Send error message
    \OCP\JSON::error(array( "data" => array( "message" => $l->t("Invalid input") )));

} else {
    // Update settings values
    \OCA\mozilla_sync\User::setAutoCreateUser($autocreate);

    // Send success message
    if ( $autocreate ) {
        \OCP\JSON::success(array( "data" => array( "message" => $l->t("Auto create sync account enabled") )));
    } else {
        \OCP\JSON::success(array( "data" => array( "message" => $l->t("Auto create sync account disabled") )));
    }
}

/* vim: set ts=4 sw=4 tw=80 noet : */
