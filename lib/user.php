<?php

/**
* ownCloud
*
* @author Michal Jaskurzynski
* @author Oliver Gasser
* @copyright 2012 Michal Jaskurzynski mjaskurzynski@gmail.com
*
*/

namespace OCA\mozilla_sync;

/**
* @brief This class provides all methods for Mozilla Sync user management.
*
* Terminology:
*	- Sync ID: Mozilla Sync user ID, unique integer. Stored in
*		oc_mozilla_sync_users.
*	- Sync hash: Mozilla Sync hashed email address, unique string. Stored in
*		oc_mozilla_sync_users.
*	- User name: ownCloud user name, unique string. Stored in oc_users and also
*		oc_mozilla_sync_users and oc_preferences.
*	- Sync Email address: Mozilla Sync email address, string. Must be unique for
*		Mozilla Sync to work. Stored in oc_preferences.
*	- OC Email address: ownCloud email address, string. Must be unique for Mozilla
*		Sync to work. Stored in oc_preferences.
*/
class User
{

	/**
	* @brief Convert email address to ownCloud user name.
	*
	* @param string $email Email address whose user name will be returned.
	* @return mixed User name on success, false otherwise.
	*/
	public static function emailToUserName($email) {
		// Try to fetch user name with Sync email
		$query = \OCP\DB::prepare('SELECT `userid` FROM `*PREFIX*preferences`
			WHERE `appid` = ? AND `configkey` = ? AND `configvalue` = ?');
		$result = $query->execute(array('mozilla_sync', 'email', $email));

		$row = $result->fetchRow();
		if ($row) {
			return $row['userid'];
		}

		// Try to fetch user name with OC email
		$query = \OCP\DB::prepare('SELECT `userid` FROM `*PREFIX*preferences`
			WHERE `appid` = ? AND `configkey` = ? AND `configvalue` = ?');
		$result = $query->execute(array('settings', 'email', $email));

		$row = $result->fetchRow();
		if ($row) {
			return $row['userid'];
		} else {
			Utils::writeLog("DB: Could not convert email address " . $email . " to user name. Make sure that emails are unique!");
			return false;
		}
	}

	/**
	* @brief Convert ownCloud user name to Mozilla Sync user ID.
	*
	* Table oc_mozilla_sync_users contains user mapping.
	*
	* @param string $userName ownCloud user name to be converted to Sync ID.
	* @return mixed Mozilla Sync user ID on success, false otherwise.
	*/
	public static function userNameToSyncId($userName) {
		$query = \OCP\DB::prepare('SELECT `id` FROM
			`*PREFIX*mozilla_sync_users` WHERE `username` = ?');
		$result = $query->execute(array($userName));

		$row = $result->fetchRow();
		if ($row) {
			return (int) ($row['id']);
		} else {
			Utils::writeLog("DB: Could not convert user name to Sync ID.");
			return false;
		}
	}

	/**
	* @brief Convert Mozilla Sync user hash to ownCloud user name.
	*
	* Table oc_mozilla_sync_users contains user mapping.
	*
	* @param string $syncHash Mozilla Sync user hash to be converted to ownCloud
	*	user name.
	* @return mixed Sync hash on success, false otherwise.
	*/
	public static function syncHashToUserName($syncHash) {
		$query = \OCP\DB::prepare('SELECT `username` FROM
			`*PREFIX*mozilla_sync_users` WHERE `sync_user` = ?');
		$result = $query->execute(array($syncHash));

		$row = $result->fetchRow();
		if ($row) {
			return $row['username'];
		} else {
			Utils::writeLog("DB: Could not convert Sync hash " . $syncHash . " to user name.");
			return false;
		}
	}

	/**
	* @brief Convert Mozilla Sync user hash to Mozilla Sync user ID.
	*
	* @param string $syncHash Mozilla Sync user hash to be converted to Mozilla
	*	Sync user ID.
	* @return mixed Sync user ID on success, false otherwise.
	*/
	public static function syncHashToSyncId($syncHash) {
		$query = \OCP\DB::prepare('SELECT `id` FROM
			`*PREFIX*mozilla_sync_users` WHERE `sync_user` = ?');
		$result = $query->execute(array($syncHash));

		$row = $result->fetchRow();
		if ($row) {
			return $row['id'];
		} else {
			Utils::writeLog("DB: Could not convert Sync hash " . $userHash . " to Sync ID.");
			return false;
		}
	}

	/**
	* @brief Create a new Mozilla Sync user.
	*
	* @param string $syncHash The Mozilla Sync user hash of the new user.
	* @param string $password The password of the new user.
	* @param string $email The email address of the new user.
	* @return bool True on success, false otherwise.
	*/
	public static function createUser($syncHash, $password, $email) {

		// Convert email address to user name
		$userName = self::emailToUserName($email);
		if ($userName === false) {
			Utils::writeLog("Could not convert email address to user name.");
			return false;
		}

		// Verify that the provided password matches the one stored in the ownCloud database
		if (self::checkPassword($userName, $password) === false) {
			Utils::writeLog("Password for user " . $userName . " did not match.");
			return false;
		}

		$query = \OCP\DB::prepare('INSERT INTO `*PREFIX*mozilla_sync_users`
			(`username`, `sync_user`) VALUES (?, ?)' );
		$result = $query->execute(array($userName, $syncHash));

		if ($result == false) {
			Utils::writeLogDbError("DB: Could not create user " . $userName . " with Sync hash " . $syncHash . ".", $query);
			return false;
		}

		return true;
	}

	/**
	* @brief Delete Mozilla Sync user.
	*
	* @param integer $syncId Mozilla Sync user ID of the user to be deleted.
	* @return bool True on success, false otherwise.
	*/
	public static function deleteUser($syncId) {
		$query = \OCP\DB::prepare('DELETE FROM `*PREFIX*mozilla_sync_users`
			WHERE `id` = ?');
		$result = $query->execute(array($syncId));

		if ($result == false) {
			Utils::writeLogDbError("DB: Could not delete user with Sync ID " . $syncId . ".", $query);
			return false;
		}

		return true;
	}

	/**
	* @brief Check if there is already a Mozilla Sync account for this Mozilla
	*	Sync user hash.
	*
	* @param string $syncHash The Mozilla Sync user hash to be checked.
	* @return bool True if the user exists, false otherwise.
	*/
	public static function syncUserExists($syncHash) {
		$query = \OCP\DB::prepare('SELECT COUNT(*) AS `count` FROM `*PREFIX*mozilla_sync_users` WHERE `sync_user` = ?');
		$result = $query->execute(array($syncHash));

		$row = $result->fetchRow();

		return (((int) $row['count']) === 1);
	}

	/**
	* @brief Authenticate user by HTTP Basic Authentication with user name and
	*	password.
	*
	* @param string $syncHash Mozilla Sync user hash parameter extracted from
	*	the URL.
	* @return bool True on authentication success, false otherwise.
	*/
	public static function authenticateUser($syncHash) {

		if (!isset($_SERVER['PHP_AUTH_USER'])) {
			Utils::writeLog("No HTTP authentication header sent.");
			return false;
		}

		// Sync hash URL parameter and HTTP Authentication header user name do not match
		if ($syncHash != $_SERVER['PHP_AUTH_USER']) {
			Utils::writeLog("Sync hash URL parameter " . $syncHash . " and HTTP Authentication header " . $_SERVER['PHP_AUTH_USER'] . " do not match.");
			return false;
		}

		// Get user name corresponding to Sync hash
		$userName = self::syncHashToUserName($syncHash);
		if ($userName === false) {
			return false;
		}

		// Check the password in the ownCloud database
		return self::checkPassword($userName, $_SERVER['PHP_AUTH_PW']);
	}

	/**
	* @brief Checks the password of a user. Additionally verifies whether user
	*	is member of group that is allowed to use Mozilla Sync.
	*
	* Checks the supplied password for the user. If the LDAP app is also
	* active it tries to authenticate against it as well. For this to work the
	* User Login Filter in the admin panel needs to be set to something
	* like (|(uid=%uid)(mail=$uid)) .
	*
	* @param string $userName ownCloud user name whose password will be checked.
	* @param string $password ownCloud password.
	* @return bool True if the password is correct, false otherwise.
	*
	*/
	private static function checkPassword($userName, $password) {

		// Enable authentication app, necessary for LDAP to work
		\OC_App::loadApps(array('authentication'));

		// Check if user is allowed to use Mozilla Sync
		if (self::checkUserIsAllowed($userName) === false) {
			return false;
		}

		// Check password normally
		if (\OCP\User::checkPassword($userName, $password) != false) {
			return true;
		}

		// Check if the LDAP app is enabled
		if (\OCP\App::isEnabled('user_ldap')) {
			// Convert user name to email address
			$email = self::userNameToEmail($userName);

			if ($email === false) {
				return false;
			}

			// Check password with email instead of user name as internal
			// ownCloud user name and LDAP user ID are likely not to match
			$res = (\OCP\User::checkPassword($email, $password) != false);
			if ($res === false) {
				Utils::writeLog("LDAP password did not match for user " . $userName . " with email address " . $email . ".");
			}
			return $res;
		}

		Utils::writeLog("Password did not match for user " . $userName . ".");
		return false;
	}


	/**
	* @brief Convert ownCloud user name to email address. If an email address
	*	was deduced, update it in the database.
	*
	* @param string $userName User name to be converted to email address. The
	*	currently logged in user by default.
	* @return mixed Email address on success, false otherwise.
	*/
	public static function userNameToEmail($userName = null) {
		// By default the user name is the currently logged in user
		if (is_null($userName)) {
			$userName = \OCP\User::getUser();
		}

		// Try to get Sync email address
		$email = \OCP\Config::getUserValue($userName, 'mozilla_sync', 'email');
		if ($email) {
			return $email;
		}

		// Try to get OC password-restore email address
		$email = \OCP\Config::getUserValue($userName, 'settings', 'email');
		if ($email) {
			// Update Sync email in database
			self::setEmail($email, $userName);
			return $email;
		}

		// Check if user name is already an email address
		if(filter_var($userName, FILTER_VALIDATE_EMAIL)) {
			$email = $userName;
			// Update Sync email in database
			self::setEmail($email, $userName);
			return $email;
		} else {
			Utils::writeLog("Could not convert user name " . $userName . " to email address. Make sure that emails are unique!",
				\OCP\Util::INFO);
			return false;
		}
	}

	/**
	* @brief Check if the currently logged in user has a unique email address.
	*
	* @param string $userName User name checking for duplicate email addresses.
	*	By default the currently logged in user.
	* @return bool True if the user's email is unique, false otherwise.
	*/
	public static function userHasUniqueEmail($userName = null) {
		// By default the user name is the currently logged in user
		if (is_null($userName)) {
			$userName = \OCP\User::getUser();
		}

		// Return false if there is no user logged in
		if ($userName === false) {
			return false;
		}

		$email = self::userNameToEmail($userName);

		// Return false if the user did not set an email address
		if ($email === false) {
			return false;
		}

		// Check for duplicate Sync email addresses
		$query = \OCP\DB::prepare('SELECT COUNT(*) AS `count` FROM `*PREFIX*preferences` WHERE `appid` = ? AND `configkey` = ? AND `configvalue` = ?');
		$result = $query->execute(array('mozilla_sync', 'email', $email));

		// Only return true if exactly one row matched for this email address
		$row = $result->fetchRow();
		return (((int) $row['count']) === 1);
	}

	/**
	* @brief Checks whether a user is allowed to use the Mozilla Sync service.
	*
	* @param string $userName The user's user name. Defaults to the currently logged in user.
	* @return bool True if the user is allowed to use Mozilla Sync, false
	*	otherwise.
	*/
	public static function checkUserIsAllowed($userName = null) {
		$authorizedGroup = self::getAuthorizedGroup();

		// First check if group restriction is enabled
		if ($authorizedGroup === false) {
			return true;
		}

		// By default the user name is the currently logged in user
		if (is_null($userName)) {
			$userName = \OCP\User::getUser();
		}

		// Check if user is member of allowed group
		if (self::checkGroupMembership($userName, $authorizedGroup) === true) {
			return true;
		}

		// User is not allowed to use Mozilla Sync
		Utils::writeLog("User " . $userName . " is not part of the " .
			$authorizedGroup . " group and thus is not allowed to use Mozilla Sync.");
		return false;
	}

	/**
	* @brief Checks whether a user is a member of the specified group.
	*
	* @param string $userName The ownCloud user name of the user to be checked
	*	for group membership.
	* @param string $groupName The group name to be checked.
	* @return bool True if user is in group, false otherwise.
	*/
	private static function checkGroupMembership($userName, $groupName) {
		// Check if user is member of group
		$query = \OCP\DB::prepare('SELECT COUNT(*) AS `count` FROM `*PREFIX*group_user` WHERE `uid` = ? AND `gid` = ?');
		$result = $query->execute(array($userName, $groupName));

		// Only return true if exactly one row matched for this email address
		$row = $result->fetchRow();
		return (((int) $row['count']) === 1);
	}

	/**
	* @brief Gets the group that is authorized to utilize the Mozilla Sync
	*	service.
	*
	* It is possible to restrict the usage of Mozilla Sync to users who are
	* members of a certain group. This feature is disabled by default but can be
	* enabled on the admin page.
	*
	* @return mixed The group name or false if everyone can use Mozilla Sync.
	*/
	public static function getAuthorizedGroup() {
		$group = \OCP\Config::getAppValue('mozilla_sync', 'authorized_group');
		if (is_null($group)) {
			return false;
		} else {
			return $group;
		}
	}

	/**
	* @brief Sets the group that is authorized to utilize the Mozilla Sync
	*	service.
	*
	* It is possible to restrict the usage of Mozilla Sync to users who are
	* members of a certain group. This feature is disabled by default but can be
	* enabled on the admin page.
	*
	* @param mixed $group The group name or null if everyone can use Mozilla Sync.
	*/
	public static function setAuthorizedGroup($group = null) {
		\OCP\Config::setAppValue('mozilla_sync', 'authorized_group', $group);
	}

	/**
	* @brief Gets all ownCloud groups.
	*
	* @return Array containing all ownCloud groups.
	*/
	public static function getAllGroups() {
		$query = \OCP\DB::prepare('SELECT `gid` FROM `*PREFIX*groups`');
		$result = $query->execute();

		// Collect all groups in this array
		$groups = array();

		while ($row = $result->fetchRow()) {
			$groups[] = $row['gid'];
		}
		return $groups;
	}

	/**
	* @brief Check if an ownCloud user has an associated Mozilla Sync account.
	*
	* Table oc_mozilla_sync_users contains user mapping.
	*
	* @param string $userName ownCloud user name to be checked for Sync account.
	* @return mixed True if Sync account is present, false otherwise.
	*/
	public static function hasSyncAccount($userName = null) {
		// By default the user name is the currently logged in user
		if (is_null($userName)) {
			$userName = \OCP\User::getUser();
		}

		$query = \OCP\DB::prepare('SELECT COUNT(*) AS `count` FROM `*PREFIX*mozilla_sync_users` WHERE `username` = ?');
		$result = $query->execute(array($userName));

		// Only return true if exactly one row matched for this user name
		$row = $result->fetchRow();
		return (((int) $row['count']) === 1);
	}

	/**
	* @brief Gets the usage for the given user.
	*
	* Returns the current usage for a specific user in kB. For SQLite
	* the number of thousand characters are returned, since there is no byte length
	* function for SQLite databases.
	* It is possible to restrict the quota of Mozilla Sync to a limit. A zero
	* limit results in no restriction. The value is zero by default but can be
	* set on the admin page.
	*
	* @param int $syncId The user's Sync ID whose usage will be returned.
	* @return float The usage in kB.
	*/
	public static function getUserUsage($syncId) {
		// Sum up character size of all WBO
		$query = \OCP\DB::prepare('SELECT SUM(LENGTH(`payload`)) as `size` FROM `*PREFIX*mozilla_sync_wbo` JOIN `*PREFIX*mozilla_sync_collections` ON `*PREFIX*mozilla_sync_wbo`.`collectionid` = `*PREFIX*mozilla_sync_collections`.`id` WHERE `userid` = ?');
		$result = $query->execute(array($syncId));

		if ($result == false) {
			Utils::writeLogDbError("DB: Could not get info quota for user " . $syncId . ".", $query);
			return false;
		}

		$row = $result->fetchRow();
		return ((float) ($row['size']))/1024.0;
	}

	/**
	* @brief Gets the quota for all Sync accounts.
	*
	* It is possible to restrict the quota of Mozilla Sync to a limit. A zero
	* limit results in no restriction. The value is zero by default but can be
	* set on the admin page.
	*
	* @return integer The quota in kB or 0 if no quota is set.
	*/
	public static function getQuota() {
		return ((int) \OCP\Config::getAppValue('mozilla_sync', 'quota_limit', '0'));
	}

	/**
	* @brief Sets the quota for all sync accounts.
	*
	* It is possible to restrict the quota of Mozilla Sync to a limit. A zero
	* limit results in no restriction. The value is zero by default but can be
	* set on the admin page.
	*
	* @param integer $quota The quota to be set in kB or zero if quota is to be
	*	deactivated.
	*/
	public static function setQuota($quota = 0) {
		\OCP\Config::setAppValue('mozilla_sync', 'quota_limit', $quota);
	}

	/**
	* @brief Sets the Sync email for the currently logged in user.
	*
	* @param integer $email The email address to set for the user.
	* @param string $userName The user's user name. Defaults to the currently logged in user.
	*/
	public static function setEmail($email, $userName = null) {
		// By default the user name is the currently logged in user
		if (is_null($userName)) {
			$userName = \OCP\User::getUser();
		}

		\OCP\Config::setUserValue($userName, 'mozilla_sync', 'email', $email);
	}
}

/* vim: set ts=4 sw=4 tw=80 noet : */
