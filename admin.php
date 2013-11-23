<?php

// Check if user is admin, redirect to home if not
\OCP\User::checkAdminUser();

// Adds a javascript file
//\OCP\Util::addScript( "apptemplate", "admin" );

// Assign admin template
$tmpl = new \OCP\Template('mozilla_sync', 'admin');

$tmpl->assign('mozillaSyncRestrictGroupEnabled', \OCA\mozilla_sync\User::getAuthorizedGroup());

return $tmpl->fetchPage();

