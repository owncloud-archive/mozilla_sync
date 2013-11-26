<?php

/**
* ownCloud
*
* @author Andreas Ihrig
* @copyright 2013 Andreas Ihrig
*
*/

// Check if valid requesttoken was sent
\OCP\JSON::callCheck();

// Load translations
$l=OC_L10N::get('core');

// Get userId and try to delete the user
$userId = \OCA\mozilla_sync\User::userNameToUserId(\OCP\User::getUser());
if ($userId) {
    // delete storage and user
    if (\OCA\mozilla_sync\Storage::deleteStorage($userId) === false) {
        // Send error message
        \OCP\JSON::error(array( "data" => array( "message" => $l->t("Failed to delete storage") )));
    }
    else {
        if (\OCA\mozilla_sync\User::deleteUser($userId) === false) {
            // Send error message
            \OCP\JSON::error(array( "data" => array( "message" => $l->t("Failed to delete user") )));
        }
        else {
            // Send success message
            \OCP\JSON::success(array( "data" => array( "message" => $l->t("Storage deleted") )));
        }
    }
}
else {
    // Send error message
    \OCP\JSON::error(array( "data" => array( "message" => $l->t("User not found") )));
}

/* vim: set ts=4 sw=4 tw=80 noet : */
