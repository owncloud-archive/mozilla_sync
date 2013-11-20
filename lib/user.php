<?php

/**
* ownCloud
*
* @author Michal Jaskurzynski
* @copyright 2012 Michal Jaskurzynski mjaskurzynski@gmail.com
*
*/

namespace OCA_mozilla_sync;

/**
* @brief This class provides all methods for mozilla sync service user management.
*/
class User
{

	/**
	* @brief Find owncloud userid by email address
	*
	* @param string $email
	*/
	public static function emailToUserId($email) {
		$query = \OCP\DB::prepare( 'SELECT `userid` FROM `*PREFIX*preferences` WHERE `appid` = ? AND `configkey` = ? AND `configvalue` = ?');
		$result = $query->execute( array('settings', 'email', $email) );

		$row=$result->fetchRow();
		if($row) {
			return $row['userid'];
		}
		else{
			Utils::writeLog("DB: Could not convert email address " . $email . " to user ID. Make sure that emails are unique!");
			return false;
		}
	}

	/**
	* @brief Change sync user hash to owncloud user name
	*
	* Table oc_mozilla_sync_users contain user mapping
	*
	* @param string $userHash
	*/
	public static function userHashToUserName($userHash) {
		$query = \OCP\DB::prepare( 'SELECT `username` FROM `*PREFIX*mozilla_sync_users` WHERE `sync_user` = ?');
		$result = $query->execute( array($userHash) );

		$row=$result->fetchRow();
		if($row) {
			return $row['username'];
		}
		else{
			Utils::writeLog("DB: Could not convert user hash " . $userHash . " to user name.");
			return false;
		}
	}

	public static function userHashToId($userHash) {
		$query = \OCP\DB::prepare( 'SELECT `id` FROM `*PREFIX*mozilla_sync_users` WHERE `sync_user` = ?');
		$result = $query->execute( array($userHash) );

		$row=$result->fetchRow();
		if($row) {
			return $row['id'];
		}
		else{
			Utils::writeLog("DB: Could not convert user hash " . $userHash . " to user ID.");
			return false;
		}
	}

	/**
	* @brief Create a new user
	*
	* @param string $syncUserHash The username of the user to create
	* @param string $password The password of the new user
	* @returns boolean
	*/
	public static function createUser($syncUserHash, $password, $email) {

		$userId = self::emailToUserId($email);
		if($userId == false) {
			return false;
		}

		if(self::checkPassword($userId, $password) == false) {
			Utils::writeLog("Password for user ID " . $userId . " did not match.");
			return false;
		}

		$query = \OCP\DB::prepare( 'INSERT INTO `*PREFIX*mozilla_sync_users` (`username`, `sync_user`) VALUES (?,?)' );
		$result = $query->execute( array($userId, $syncUserHash) );

		if($result == false) {
			Utils::writeLog("DB: Could not create user " . $userId . " with user hash " . $syncUserHash . ".");
			return false;
		}

		return true;
	}

	/**
	* @biref Delete user
	*
	* @param integer $userId
	* @return boolean true if success
	*/
	public static function deleteUser($userId) {
		$query = \OCP\DB::prepare( 'DELETE FROM `*PREFIX*mozilla_sync_users` WHERE `id` = ?');
		$result = $query->execute( array($userId) );

		if($result == false) {
			Utils::writeLog("DB: Could not delete user " . $userId . ".");
			return false;
		}

		return true;
	}

	/**
	* @brief Check if user has sync account
	*
	* @param string $userHash The sync hash of the user to check
	* @returns boolean
	*/
	public static function syncUserExists($userHash) {
		$query = \OCP\DB::prepare( 'SELECT 1 FROM `*PREFIX*mozilla_sync_users` WHERE `sync_user` = ?');
		$result = $query->execute( array($userHash) );

		return ((int) $result->numRows()) === 1;
	}

	/**
	* @brief Authenticate user by HTTP Basic Authorization user and password
	*
	* @param string $userHash User hash parameter specified by Url parameter
	* @return boolean
	*/
	public static function authenticateUser($userHash) {

		if(!isset($_SERVER['PHP_AUTH_USER'])) {
			Utils::writeLog("No HTTP authentication header sent.");
			return false;
		}
		// user name parameter and authentication user name doen't match
		if($userHash != $_SERVER['PHP_AUTH_USER']) {
			Utils::writeLog("User name parameter " . $userHash . " and HTTP authentication header " . $_SERVER['PHP_AUTH_USER'] . " do not match.");
			return false;
		}

		$userId = self::userHashToUserName($userHash);
		if($userId == false) {
			return false;
		}

		return self::checkPassword($userId, $_SERVER['PHP_AUTH_PW']);
	}

	/**
	* @brief Checks the password of a user
	* @param string $userId User ID of the user
	* @param string $password Password of the user
	* @return boolean True if the password is correct, false otherwise
	*
	* Checks the supplied password for the user. If the LDAP app is also
	* active it tries to authenticate as well. For this to work the
	* User Login Filter in the admin panel needs to be set to something
	* like (|(uid=%uid)(mail=$uid)) .
	*/
	private static function checkPassword($userId, $password) {

		if (\OCP\User::checkPassword($userId, $password) != false) {
			return true;
		}

		// Check if the LDAP app is enabled
		$ldap_enabled = \OCP\Config::getAppValue('user_ldap', 'enabled');
		if ($ldap_enabled === 'yes') {
			// Convert user ID to email address
			$email = self::userIdToEmail($userId);

			if ($email == false) {
				return false;
			}

			// Check password with email instead of user ID as internal
			// Owncloud ID and LDAP user ID are likely not to match
			$res = (\OCP\User::checkPassword($email, $password) != false);
			if ($res === false) {
				Utils::writeLog("LDAP password did not match for user " . $userId . " with email address " . $email . ".");
			}
			return $res;
		}

		Utils::writeLog("Password did not match for user " . $userId . ".");

		return false;
	}


	/**
	* @brief Find email address by Owncloud user ID
	*
	* @param string $userId
	*/
	private static function userIdToEmail($userId) {
		$email = \OCP\Config::getUserValue($userId, 'settings', 'email');

		if ($email) {
			return $email;
		} else {
			Utils::writeLog("Could not convert user ID " . $userId . " to email address. Make sure that emails are unique!");
			return false;
		}
	}
}

/* vim: set ts=4 sw=4 tw=80 noet : */
