<?php

// load user backends
OC_App::loadApps($RUNTIME_APPTYPES);

$url = OCA_mozilla_sync\Utils::getSyncUrl();
if( $url === false ) {
  OCA_mozilla_sync\Utils::changeHttpStatus(404);
  exit();
}

$service = OCA_mozilla_sync\Utils::getServiceType();

$urlParser = new OCA_mozilla_sync\UrlParser($url);
if(!$urlParser->isValid()) {
  OCA_mozilla_sync\Utils::changeHttpStatus(404);
  exit();
}

if($service === 'userapi') {
  OCA_mozilla_sync\Utils::generateMozillaTimestamp();
  $userService = new OCA_mozilla_sync\UserService($urlParser);
  $userService->run();
}
else if($service === 'storageapi') {
  $storageService = new OCA_mozilla_sync\StorageService($urlParser);
  $storageService->run();
}


/* vim: set ts=4 sw=4 tw=80 noet : */
