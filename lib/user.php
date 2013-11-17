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
			return false;
		}

		$query = \OCP\DB::prepare( 'INSERT INTO `*PREFIX*mozilla_sync_users` (`username`, `sync_user`) VALUES (?,?)' );
		$result = $query->execute( array($userId, $syncUserHash) );

		if($result == false) {
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
			return false;
		}
		// user name parameter and authentication user name doen't match
		if($userHash != $_SERVER['PHP_AUTH_USER']) {
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
		$query = \OCP\DB::prepare('SELECT 1 FROM `*PREFIX*appconfig` WHERE `appid` = ? AND `configkey` = ? AND `configvalue` = ?');
		$result = $query->execute(array('user_ldap', 'enabled', 'yes'));

		if ((int) $result->numRows() === 1) {
			// Convert user ID to email address
			$email = self::userIdToEmail($userId);

			if ($email == false) {
				return false;
			}

			// Check password with email instead of user ID as internal
			// Owncloud ID and LDAP user ID are likely not to match
			return (\OCP\User::checkPassword($email, $password) != false);
		}

		return false;
	}


	/**
	* @brief Find email address by Owncloud userid
	*
	* @param string $userId
	*/
	private static function userIdToEmail($userId) {
		$query = \OCP\DB::prepare('SELECT `configvalue` FROM `*PREFIX*preferences` WHERE `userid` = ? AND `appid` = ? AND `configkey` = ?');
		$result = $query->execute(array($userId, 'settings', 'email'));

		$row = $result->fetchRow();
		if ($row) {
			return $row['configvalue'];
		}
		else {
			return false;
		}
	}
}

/* vim: set ts=4 sw=4 tw=80 noet : */
