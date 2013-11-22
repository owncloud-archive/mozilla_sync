<?php

/**
 * This is called by Sync clients when they access the Sync API
 * on this ownCloud server.
 *
 * @author Michal Jaskurzynski 
 * @author Oliver Gasser
 */

// Load user backends
OC_App::loadApps($RUNTIME_APPTYPES);

// Get sync URL
$url = OCA_mozilla_sync\Utils::getSyncUrl();
if($url === false) {
	OCA_mozilla_sync\Utils::changeHttpStatus(404);
	exit();
}

// Parse and validate the URL accessed by the client
$urlParser = new OCA_mozilla_sync\UrlParser($url);
if(!$urlParser->isValid()) {
	OCA_mozilla_sync\Utils::changeHttpStatus(404);
	exit();
}

// Get service type based on URL and determine whether to start user or storage service 
$service = OCA_mozilla_sync\Utils::getServiceType();

if($service === 'userapi') {
	// Send a timestamp header
	OCA_mozilla_sync\Utils::sendMozillaTimestampHeader();
	$userService = new OCA_mozilla_sync\UserService($urlParser);
	$userService->run();
} else if($service === 'storageapi') {
	// Note: Timestamp header will be sent later by storage API service
	$storageService = new OCA_mozilla_sync\StorageService($urlParser);
	$storageService->run();
}

/* vim: set ts=4 sw=4 tw=80 noet : */
