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
*	- Email address: ownCloud email address, string. Must be unique for Mozilla
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
	public static function userNameToUserId($userName) {
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

		if($result == false) {
			Utils::writeLog("DB: Could not create user " . $userName . " with Sync hash " . $syncHash . ".");
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
			Utils::writeLog("DB: Could not delete user with Sync ID " . $syncId . ".");
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
		$query = \OCP\DB::prepare('SELECT 1 FROM `*PREFIX*mozilla_sync_users`
			WHERE `sync_user` = ?');
		$result = $query->execute(array($syncHash));

		return (((int) $result->numRows()) === 1);
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
	* @brief Checks the password of a user.
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
	* @brief Convert ownCloud user name to email address.
	*
	* @param string $userName User name to be converted to email address.
	* @return mixed Email address on success, false otherwise.
	*/
	private static function userNameToEmail($userName) {
		$email = \OCP\Config::getUserValue($userName, 'settings', 'email');

		if ($email) {
			return $email;
		} else {
			Utils::writeLog("Could not convert user name " . $userName . " to email address. Make sure that emails are unique!");
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

		// Check for duplicate emails
		$query = \OCP\DB::prepare('SELECT 1 FROM `*PREFIX*preferences` WHERE `appid` = ? AND `configkey` = ? AND `configvalue` = ?');
		$result = $query->execute(array('settings', 'email', $email));

		// Only return true if exactly one row matched for this email address
		return ((int) $result->numRows()) === 1;
	}
}

/* vim: set ts=4 sw=4 tw=80 noet : */
