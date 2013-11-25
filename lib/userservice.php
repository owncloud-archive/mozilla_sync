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
* @brief Implementation of Mozilla Sync User Service.
*
*/
class UserService extends Service
{
	public function __construct($urlParser, $inputData = null) {
		$this->urlParser = $urlParser;
		$this->inputData = $inputData;
	}

	/**
	* @brief Run user service.
	*
	* @return True on successful command parsing, false otherwise.
	*/
	public function run() {

		// Check if the given URL is valid
		if (!$this->urlParser->isValid()) {
			Utils::changeHttpStatus(Utils::STATUS_NOT_FOUND);
			Utils::writeLog("URL: Failed to parse URL.");
			return false;
		}

		// Map request to functions
		if ($this->urlParser->commandCount() === 0) {

			$syncHash = $this->urlParser->getSyncHash();

			switch (Utils::getRequestMethod()) {
				case 'GET': $this->findUser($syncHash); break;
				case 'PUT': $this->createUser($syncHash); break;
				case 'DELETE': $this->deleteUser($syncHash); break;
				default:
					Utils::changeHttpStatus(Utils::STATUS_NOT_FOUND);
					Utils::writeLog("URL: Invalid HTTP method " . Utils::getRequestMethod() . " for user " . $syncHash . ".");
			}
		} else if (($this->urlParser->commandCount() === 1) &&
			(Utils::getRequestMethod() === 'POST')) {

			$syncHash = $this->urlParser->getSyncHash();
			$password = $this->urlParser->getCommand(0);

			$this->changePassword($syncHash, $password);
		} else if ($this->urlParser->commandMatch('/node\/weave/')) {
			$this->getSyncServer();
		} else {
			Utils::changeHttpStatus(Utils::STATUS_NOT_FOUND);
			Utils::writeLog("URL: Invalid URL.");
		}

		return true;
	}

	/**
	*  @brief Checking if Mozilla Sync user already exists.
	*
	*  GET https://server/pathname/version/username
	*
	*  Returns 1 if the username is in use, 0 if it is available. The answer is in plain text.
	*
	*  Possible errors:
	*    503: there was an error getting the information
	*
	* @param string $syncHash The Mozilla Sync user hash to be checked for
	*	existence.
	* @return bool True if user exists, false otherwise.
	*/
	private function findUser($syncHash) {
		if (User::syncUserExists($syncHash)) {
			OutputData::write('1');
			return true;
		} else {
			OutputData::write('0');
			return false;
		}
	}

	/**
	*  @brief Send storage API server address back to the client.
	*
	*  GET https://server/pathname/version/username/node/weave
	*
	*  Sends the Weave (aka Sync) Node that the client is located on. Sync-specific calls should be directed to that node.
	*  The node URL is sent as an unadorned (not JSON) string. It may be ‘null’ if no node can be assigned at this time, probably due to sign up throttling.
	*
	*  Possible errors:
	*    503: there was an error getting a node | empty body
	*    404: user not found | empty body
	*
	*/
	private function getSyncServer() {
		OutputData::write(Utils::getServerAddress());
	}

	/**
	*  @brief Create a new Mozilla Sync user.
	*
	*  PUT https://server/pathname/version/username
	*
	*  Requests that an account be created for username.
	*
	*  The body is a JSON mapping and should include:
	*    password: the password to be associated with the account.
	*    e-mail: Email address associated with the account.
	*    captcha-challenge: The challenge string from the captcha.
	*    captcha-response: The response to the captcha.
	*
	*  An X-Weave-Secret can be provided containing a secret string known by the server.
	*  When provided, it will override the captcha. This is useful for testing and automation.
	*
	*  The server will return the lowercase username on success.
	*
	*  Possible errors:
	*    503: there was an error creating the reset code
	*    400: 4 (user already exists)
	*    400: 6 (Json parse failure)
	*    400: 12 (No email address on file)
	*    400: 7 (Missing password field)
	*    400: 9 (Requested password not strong enough)
	*    400: 2 (Incorrect or missing captcha)
	*
	*  @param string $userHash Mozilla Sync user hash for the user to be
	*	created.
	*/
	private function createUser($syncHash) {

		$inputData = $this->getInputData();

		// JSON parse failure
		if (!$inputData->isValid()) {
			Utils::sendError(400, 6);
			Utils::writeLog("Failed to parse JSON for user " . $syncHash . ".");
		}

		// No password sent
		if (!$inputData->hasValue('password')) {
			Utils::sendError(400, 7);
			Utils::writeLog("Request for user " . $syncHash . " did not include a password.");
		}

		// No email sent
		if (!$inputData->hasValue('email')) {
			Utils::sendError(400, 12);
			Utils::writeLog("Request for user " . $syncHash . " did not include an email.");
		}

		// User already exists
		if (User::syncUserExists($syncHash)) {
			Utils::sendError(400, 4);
			Utils::writeLog("Failed to create user " . $syncHash . ". User already exists.");
		}

		// Create a new user
		if (User::createUser($syncHash, $inputData->getValue('password'), $inputData->getValue('email'))) {
			OutputData::write(strtolower($syncHash));
		} else {
			Utils::sendError(400, 12);
			Utils::writeLog("Failed to create user " . $syncHash . ".");
		}
	}

	/**
	*  @brief Delete a Mozilla Sync user.
	*
	*  DELETE https://server/pathname/version/username
	*
	*  Deletes the user account.
	*  NOTE: Requires simple authentication with the username and password associated with the account.
	*
	*  Return value:
	*  0 on success
	*
	*  Possible errors:
	*    503: there was an error removing the user
	*    404: the user does not exist in the database
	*    401: authentication failed
	*
	*  @param string $syncHash Mozilla Sync user hash of the user to be deleted.
	*/
	private function deleteUser($syncHash) {

		if (User::syncUserExists($syncHash) === false) {
			Utils::changeHttpStatus(Utils::STATUS_NOT_FOUND);
			Utils::writeLog("Failed to delete user " . $syncHash . ". User does not exist.");
		}

		if (User::authenticateUser($syncHash) === false) {
			Utils::changeHttpStatus(Utils::STATUS_INVALID_USER);
			Utils::writeLog("Authentication for deleting user " . $syncHash . " failed.");
		}

		$userId = User::syncHashToSyncId($syncHash);
		if ($userId === false) {
			Utils::changeHttpStatus(Utils::STATUS_INVALID_USER);
			Utils::writeLog("Failed to convert user " . $syncHash . " to user ID.");
		}

		if (Storage::deleteStorage($userId) === false) {
			Utils::changeHttpStatus(Utils::STATUS_MAINTENANCE);
			Utils::writeLog("Failed to delete storage for user " . $userId . ".");
		}

		if (User::deleteUser($userId) === false) {
			Utils::changeHttpStatus(Utils::STATUS_MAINTENANCE);
			Utils::writeLog("Failed to delete user " . $userId . ".");
		}

		OutputData::write('0');
	}

	/**
	*  @brief Change Mozilla Sync password.
	*
	* CANNOT BE IMPLEMENTED! PASSWORD NEEDS TO BE CHANGED INSIDE OWNCLOUD!
	*
	*  POST https://server/pathname/version/username/password
	*
	*  Changes the password associated with the account to the value specified in the POST body.
	*
	*  NOTE: Requires basic authentication with the username and (current) password associated with the account.
	*  The auth username must match the username in the path.
	*
	*  Alternately, a valid X-Weave-Password-Reset header can be used, if it contains a code previously obtained from the server.
	*
	*  Return values: “success” on success.
	*
	*  Possible errors:
	*    400: 7 (Missing password field)
	*    400: 10 (Invalid or missing password reset code)
	*    400: 9 (Requested password not strong enough)
	*    404: the user does not exists in the database
	*    503: there was an error updating the password
	*    401: authentication failed
	*
	* @param string $syncHash The Mozilla Sync user hash of the user that wants
	*	to change the password.
	* @param string $password The password.
	*/
	private function changePassword($syncHash, $password) {
		Utils::writeLog("Changing password failed! To change your password for Mozilla Sync, please use ownCloud's password changing function.");
		// OutputData::write('success');
	}
}

/* vim: set ts=4 sw=4 tw=80 noet : */
