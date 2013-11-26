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
* @brief Implementation of Mozilla Sync Storage Service.
*
*/
class StorageService extends Service
{
	public function __construct($urlParser, $inputData = null) {
		$this->urlParser = $urlParser;
		$this->inputData = $inputData;
	}

	/**
	* @brief Run storage service.
	*
	* @return True on successful command parsing, false otherwise.
	*/
	public function run() {
		// Check if given url is valid
		if (!$this->urlParser->isValid()) {
			Utils::changeHttpStatus(Utils::STATUS_INVALID_DATA);
			Utils::writeLog("URL: Invalid URL.");
			return false;
		}

		// Get Mozilla Sync user hash and authenticate user
		$syncHash = $this->urlParser->getSyncHash();
		if (User::authenticateUser($syncHash) === false) {
			Utils::changeHttpStatus(Utils::STATUS_INVALID_USER);
			Utils::writeLog("Could not authenticate user " . $syncHash . ".");
			return false;
		}

		// Convert Sync hash to user ID
		$userId = User::syncHashToSyncId($syncHash);
		if ($userId == false) {
			Utils::changeHttpStatus(Utils::STATUS_INVALID_USER);
			Utils::writeLog("Could not convert user " . $syncHash . " to user ID.");
			return false;
		}

		// Delete old WBO on every run of storage service
		Storage::deleteOldWbo();

		// Map request to functions

		// Info case: https://server/pathname/version/username/info/
		if (($this->urlParser->commandCount() == 2) &&
				($this->urlParser->getCommand(0) == 'info')) {

			if (Utils::getRequestMethod() != 'GET') {
				Utils::changeHttpStatus(Utils::STATUS_NOT_FOUND);
				Utils::writeLog("URL: Invalid HTTP method " . Utils::getRequestMethod() . " for info.");
				return false;
			}

			switch ($this->urlParser->getCommand(1)) {
				case 'collections': $this->getInfoCollections($userId); break;
				case 'collection_usage': $this->getInfoCollectionUsage($userId); break;
				case 'collection_counts': $this->getInfoCollectionCounts($userId); break;
				case 'quota': $this->getInfoQuota($userId); break;
				default:
					Utils::changeHttpStatus(Utils::STATUS_NOT_FOUND);
					Utils::writeLog("URL: Invalid command " . $this->urlParser->getCommand(1) . " for info.");
					return false;
			}
		}

		// Storage case: https://server/pathname/version/username/storage/
		else if (($this->urlParser->commandCount() == 1) &&
				($this->urlParser->getCommand(0) == 'storage')) {

			switch (Utils::getRequestMethod()) {
				case 'DELETE': $this->deleteStorage($userId); break;
				default:
					Utils::changeHttpStatus(Utils::STATUS_NOT_FOUND);
					Utils::writeLog("URL: Invalid request method " . Utils::getRequestMethod() . " for storage.");
					return false;
			}
		}

		// Collection case: https://server/pathname/version/username/storage/collection
		else if (($this->urlParser->commandCount() == 2) &&
				($this->urlParser->getCommand(0) == 'storage')) {

			$collectionName = $this->urlParser->getCommand(1);
			$modifiers = $this->urlParser->getCommandModifiers(1);

			$collectionId = Storage::collectionNameToIndex($userId, $collectionName);

			switch (Utils::getRequestMethod()) {
				case 'GET': $this->getCollection($userId, $collectionId, $modifiers); break;
				case 'POST': $this->postCollection($userId, $collectionId); break;
				case 'DELETE': $this->deleteCollection($userId, $collectionId, $modifiers); break;
				default:
					Utils::changeHttpStatus(Utils::STATUS_NOT_FOUND);
					Utils::writeLog("URL: Invalid request method" . Utils::getRequestMethod() . " for collection.");
					return false;
			}
		}

		// WBO case: https://server/pathname/version/username/storage/collection/id
		else if (($this->urlParser->commandCount() == 3) &&
				($this->urlParser->getCommand(0) == 'storage')) {

			$collectionName = $this->urlParser->getCommand(1);
			$wboId = $this->urlParser->getCommand(2);

			$collectionId = Storage::collectionNameToIndex($userId, $collectionName);

			switch (Utils::getRequestMethod()) {
				case 'GET': $this->getWBO($userId, $collectionId, $wboId); break;
				case 'PUT': $this->putWBO($userId, $collectionId, $wboId); break;
				case 'DELETE': $this->deleteWBO($userId, $collectionId, $wboId); break;
				default:
					Utils::changeHttpStatus(Utils::STATUS_NOT_FOUND);
					Utils::writeLog("URL: Invalid request method" . Utils::getRequestMethod() . " for WBO.");
					return false;
			}

		}

		// Invalid request
		else {
			Utils::changeHttpStatus(Utils::STATUS_NOT_FOUND);
			Utils::writeLog("URL: Invalid storage service request. Sent " .	((string) $this->urlParser->commandCount()) . " commands.");
			return false;
		}

		return true;
	}

	/**
	* @brief Returns a hash of collections associated with the account, along with the last modified timestamp for each collection.
	*
	* HTTP request: GET https://server/pathname/version/username/info/collections
	*
	* Example:
	*
	* HTTP/1.0 200 OK
	* Server: PasteWSGIServer/0.5 Python/2.6.6
	* Date: Sun, 25 Mar 2012 16:29:21 GMT
	* Content-Type: application/json
	* Content-Length: 227
	* X-Weave-Records: 9
	* X-Weave-Timestamp: 1332692961.71
	*
	* {"passwords": 1332607246.46, "tabs": 1332607246.93, "clients": 1332607162.28,
	* "crypto": 1332607162.21, "forms": 1332607170.80, "meta": 1332607246.96,
	* "bookmarks": 1332607162.45, "prefs": 1332607246.72, "history": 1332607245.16}
	*
	* @param integer $userId The user ID whose info/collections will be
	* retrieved.
	* @return bool True on success, false otherwise.
	*/
	private function getInfoCollections($userId) {

		// Get collections with last modification times
		$resultArray = Storage::getCollectionModifiedTimes($userId);

		if ($resultArray === false) {
			Utils::writeLog("DB: Could not get info collections for user " . $userId . ".");
			return false;
		} else {
			OutputData::write($resultArray);
			return true;
		}
	}

	/**
	* @brief Returns a hash of collections associated with the account, along with the data volume used for each (in KB).
	*
	* HTTP request: GET https://server/pathname/version/username/info/collection_usage
	*
	* Example:
	*
	* HTTP/1.0 200 OK
	* Server: PasteWSGIServer/0.5 Python/2.6.6
	* Date: Sun, 25 Mar 2012 16:29:21 GMT
	* Content-Type: application/json
	* Content-Length: 227
	* X-Weave-Records: 9
	* X-Weave-Timestamp: 1332692961.71
	*
	* {"passwords": 258.134, "tabs": 25.258, "clients": 1.525,
	* "crypto": 0.347, "forms": 119.666, "meta": 0.343,
	* "bookmarks": 267.791, "prefs": 15.642, "history": 2577.264}
	*
	* @param integer $userId The user ID whose info/collection_sage will be
	* retrieved.
	* @return bool True on success, false otherwise.
	*/
	private function getInfoCollectionUsage($userId) {

		// Get collection with sizes
		$resultArray = Storage::getCollectionSizes($userId);

		if ($resultArray === false) {
			Utils::writeLog("DB: Could not get info collection usage for user " . $userId . ".");
			return false;
		} else {
			OutputData::write($resultArray);
			return true;
		}
	}

	/**
	* @brief Returns a hash of collections associated with the account, along with the total number of items in each collection.
	*
	* HTTP request: GET https://server/pathname/version/username/info/collection_counts
	*
	* Example:
	*
	* HTTP/1.0 200 OK
	* Server: PasteWSGIServer/0.5 Python/2.6.6
	* Date: Sun, 25 Mar 2012 16:29:21 GMT
	* Content-Type: application/json
	* Content-Length: 227
	* X-Weave-Records: 9
	* X-Weave-Timestamp: 1332692961.71
	*
	* {"passwords": 574, "tabs": 2, "clients": 4,
	* "crypto": 1, "forms": 502, "meta": 1,
	* "bookmarks": 485, "prefs": 85, "history": 5163}
	*
	* @param integer $userId The user ID whose info/collection_counts will be
	* retrieved.
	* @return bool True on success, false otherwise.
	*/
	private function getInfoCollectionCounts($userId) {

		$query = \OCP\DB::prepare('SELECT `name`, (SELECT COUNT(`payload`) FROM
			`*PREFIX*mozilla_sync_wbo` WHERE
			`*PREFIX*mozilla_sync_wbo`.`collectionid` =
			`*PREFIX*mozilla_sync_collections`.`id`) as `counts` FROM
			`*PREFIX*mozilla_sync_collections` WHERE `userid` = ?');
		$result = $query->execute(array($userId));

		if ($result == false) {
			Utils::writeLog("DB: Could not get info collection counts for user " . $userId . ".");
			return false;
		}

		$resultArray = array();

		while (($row = $result->fetchRow())) {

			// Skip empty collections
			if($row['counts'] == null) {
				continue;
			}

			$key = $row['name'];
			$value = $row['counts'];

			$resultArray[$key] = $value;
		}

		OutputData::write($resultArray);
		return true;
	}

	/**
	* @brief  Returns a list containing the user's current usage and quota (in KB). The second value will be null if no quota is defined.
	*
	* HTTP request: GET https://server/pathname/version/username/info/quota
	*
	* Example:
	*
	* HTTP/1.0 200 OK
	* Server: PasteWSGIServer/0.5 Python/2.6.6
	* Date: Sun, 25 Mar 2012 16:29:21 GMT
	* Content-Type: application/json
	* Content-Length: 227
	* X-Weave-Records: 9
	* X-Weave-Timestamp: 1332692961.71
	*
	* { 574, null }
	*
	* @param integer $userId The user ID whose info/quota will be fetched.
	* @return bool True on success, false otherwise.
	*/
	private function getInfoQuota($userId) {
        $size = User::getUserUsage($userId);

        $limit = User::getQuota();
        if($limit === 0) {
            $limit = null;
        }

        OutputData::write(array($size, $limit));
        return true;
	}
    
    /**
    * @brief Checks if user has free space according his usage and the qouta.
    *
    * It is possible to restrict the quota of Mozilla Sync to a limit. A zero 
    * limit results in no restriction. The value is zero by default but can be
    * set on the admin page.
    * 
    * @param integer $userId
    * @return boolean
    */
    private function checkUserQuota($userId, $size=0) {
        $quota = User::getQuota();
        $usage = User::getUserUsage($userId);
        
        if ($quota != 0 && ($usage + $size) >= $quota) {
            Utils::writeLog("User ".$userId." reached the sync quota: usage "
                    .$usage.", size of additional data ".$size.", quota "
                    .$quota);
            Utils::sendError(Utils::STATUS_INVALID_DATA, 14);
            return false;
        }
        return true;
    }

	/**
	* @brief Returns a list of the WBO IDs contained in a collection.
	*
	* HTTP request: GET https://server/pathname/version/username/storage/collection
	*
	* This request has additional optional parameters:
	*
	* ids:             returns the ids for objects in the collection that are in the provided comma-separated list.
	*
	* full:            if defined, returns the full WBO, rather than just the id.
	*
	*
	* predecessorid:   returns the ids for objects in the collection that are directly preceded by the id given.
	*                  Usually only returns one result.
	*
	* parentid:        returns the ids for objects in the collection that are the children of the parent id given.
	*
	*
	* older:           returns only ids for objects in the collection that have been last modified before the date given.
	*
	* newer:           returns only ids for objects in the collection that have been last modified since the date given.
	*
	* index_above:     if defined, only returns items with a higher sortindex than the value specified.
	*
	* index_below:     if defined, only returns items with a lower sortindex than the value specified.
	*
	*
	* limit:           sets the maximum number of ids that will be returned.
	*
	* offset:          skips the first n ids. For use with the limit parameter (required) to paginate through a result set.
	*
	* sort:            sorts the output.
	*                     ‘oldest’ - Orders by modification date (oldest first)
	*                     ‘newest’ - Orders by modification date (newest first)
	*                     ‘index’ - Orders by the sortindex descending (highest weight first)
	*
	* WARNING!!
	*
	* In full record mode, data are send in separate arrays, for example:
	*    {"id":"test1","modified":1234}
	*    {"id":"test2","modified":12345}
	*
	* In id only mode, identificators are send in one array, for example:
	*    ["qqweeqw","testid","nexttestid"]
	*
	* @param integer $userId The user ID whose collection will be fetched.
	* @param integer $collectionId The ID of the collection to be fetched.
	* @param array $modifiers Modifiers for the fetching (see above).
	* @return bool True on success, false otherwise.
	*/
	private function getCollection($userId, $collectionId, &$modifiers) {

		$queryArgs = array();

		// Full or ID modifier
		$queryFields = '';
		if (isset($modifiers['full'])) {
			$queryFields = '`payload`, `name` AS `id`, `modified`, `sortindex`';
		} else {
			$queryFields = '`name` AS `id`';
		}

		$whereString = 'WHERE `collectionid` = ?';
		array_push($queryArgs, $collectionId);

		// Convert the modifiers to the WHERE string
		$whereString .= Storage::modifiersToString($modifiers, $queryArgs, $limit, $offset);

		$query = \OCP\DB::prepare('SELECT ' . $queryFields .
			' FROM `*PREFIX*mozilla_sync_wbo` ' . $whereString, $limit, $offset);
		$result = $query->execute($queryArgs);

		if ($result == false) {
			Utils::writeLog("DB: Could not get collection " . $collectionId . " for user " . $userId . ".");
			return false;
		}

		// Results are sent in an array
		$resultArray = array();

		while (($row = $result->fetchRow())) {

			if (isset($modifiers['full'])) {
				// Cast returned values to the correct type
				$resultArray[] = self::forceTypeCasting($row);
			} else {
				$resultArray[] = $row['id'];
			}
		}

		// Set number of elements in header
		header('X-Weave-Records: ' . count($resultArray));

		OutputData::write($resultArray);

		return true;
	}

	/**
	* @brief Save array of WBO.
	*
	* HTTP request: POST https://server/pathname/version/username/storage/collection
	*
	* Takes an array of WBOs in the request body and iterates over them,
	* effectively doing a series of atomic PUTs with the same timestamp.
	*
	* example response:
	*   {"failed": {}, "modified": 1341650217.16, "success": ["VQYhVASVcpVI"]}
	*
	* @param integer $userId The user ID whose WBO will be saved.
	* @param integer $collectionId The collection this WBO belongs to.
	* @return bool True on success, false otherwise.
	*/
    private function postCollection($userId, $collectionId) {
        // Get and verify input data
        $inputData = $this->getInputData();
        if ((!$inputData->isValid()) &&
                        (count($inputData->getInputArray()) > 0)) {
            Utils::changeHttpStatus(Utils::STATUS_INVALID_DATA);
            Utils::writeLog("URL: Invalid data for posting collection " . $collectionId . " for user " . $userId . ".");
            return false;
        }

        // Check if user has free space on limit
        $size = strlen(serialize($inputData)); // approximate the input data size
        if(!$this->checkUserQuota($userId, $size)) {
            return false;
        }

        // Get current time to be stored in DB and returned as header
        $modifiedTime = Utils::getMozillaTimestamp();

        $resultArray["modified"] = $modifiedTime;

        $successArray = array();
        $failedArray = array();

        // Iterate through input array and store all WBO in the database
        for($i = 0; $i < count($inputData->getInputArray()); $i++) {
            $result = Storage::saveWBO($userId, $modifiedTime, $collectionId,
                $inputData[$i]);
            if ($result === true) {
                $successArray[] = $inputData[$i]['id'];
            } else {
                    $failedArray[] = $inputData[$i]['id'];
                Utils::writeLog("DB: Failed to post collection " . $collectionId . " for user " . $userId . ".", \OCP\Util::WARN);
            }
        }

        $resultArray["success"] = $successArray;
        // The failed field is a hash containing arrays
        $resultArray["failed"] = (object) $failedArray;

        // Return modification time in X-Weave-Timestamp header
        OutputData::write($resultArray, $modifiedTime);
        return true;
	}

	/**
	* @brief Deletes the collection and all contents.
	*
	* HTTP request: DELETE https://server/pathname/version/username/storage/collection
	*
	* Additional request parameters may modify the selection of which items to delete @see getCollection
	*
	* @param integer $userId The user whose collection will be deleted.
	* @param integer $collectionId The ID of the collection to be deleted.
	* @param array $modifiers Modifiers specifying the collection.
	* @return bool True on success, false otherwise.
	*/
	private function deleteCollection($userId, $collectionId, &$modifiers) {

		$queryArgs = array();

		$whereString = 'WHERE `collectionid` = ?';
		array_push($queryArgs, $collectionId);

		$whereString .= Storage::modifiersToString($modifiers, $queryArgs, $limit, $offset);

		// Delete all WBO of a collection
		$query = \OCP\DB::prepare( 'DELETE FROM `*PREFIX*mozilla_sync_wbo` ' . $whereString, $limit, $offset );
		$result = $query->execute( $queryArgs );

		if ($result == false) {
			Utils::writeLog("DB: Failed to delete WBO for collection " . $collectionId . " for user " . $userId . ".");
			return false;
		}

		// Check if no new WBO was added in the meantime
		$query = \OCP\DB::prepare('SELECT 1 FROM `*PREFIX*mozilla_sync_wbo`
			WHERE `collectionid` = ?');
		$result = $query->execute(array($collectionId));

		// No WBO found, delete entire collection
		if($result->fetchRow() == false) {
			$query = \OCP\DB::prepare('DELETE FROM
			`*PREFIX*mozilla_sync_collections` WHERE `id` = ?');
			$result = $query->execute(array($collectionId));

			if ($result == false) {
				Utils::writeLog("DB: Failed to delete collection " . $collectionId . " for user " . $userId . ".");
				return false;
			}
		}

		OutputData::write(Utils::getMozillaTimestamp());
		return true;
	}

	/**
	* @brief Returns the WBO in the collection corresponding to the requested
	* ID.
	*
	* HTTP request: GET https://server/pathname/version/username/storage/collection/id
	*
	* @param integer $userId The user requesting the WBO.
	* @param integer $collectionId The collection the WBO belongs to.
	* @param integer $wboId The WBO's ID.
	* @return bool True on success, false otherwise.
	*/
	private function getWBO($userId, $collectionId, $wboId) {
		$query = \OCP\DB::prepare('SELECT `sortindex`, `payload`, `name` AS
			`id`, `modified` FROM `*PREFIX*mozilla_sync_wbo` WHERE
			`collectionid` = ? AND `name` = ?');
		$result = $query->execute(array($collectionId, $wboId));

		if ($result == false) {
			Utils::writeLog("DB: Failed to get WBO " . $wboId . " of collection " . $collectionId . " for user " . $userId . ".");
			return false;
		}

		$row = $result->fetchRow();
		if ($row == false) {
			Utils::changeHttpStatus(Utils::STATUS_NOT_FOUND);
			Utils::writeLog("DB: Could not find requested WBO " . $wboId . " of collection " . $collectionId . " for user " . $userId . ".", \OCP\Util::WARN);
			return true;
		}

		// Cast returned values to the correct type
		$row = self::forceTypeCasting($row);

		OutputData::write($row);
		return true;
	}

	/**
	* @brief Adds the WBO defined in the request body to the collection.
	*
	* HTTP request: PUT https://server/pathname/version/username/storage/collection/id
	*
	* If the WBO does not contain a payload, it will only update the provided metadata fields on an already defined object.
	* The server will return the timestamp associated with the modification.
	*
	* @param integer $userId The user the WBO belongs to.
	* @param integer $collectionId The collection the WBO belongs to.
	* @param integer $wboId The WBO's ID.
	* @return bool True on success, false otherwise.
	*/
	private function putWBO($userId, $collectionId, $wboId) {
		// Get and validate input data
		$inputData = $this->getInputData();
		if ((!$inputData->isValid()) &&
				(count($inputData->getInputArray()) == 1)) {
			Utils::changeHttpStatus(Utils::STATUS_INVALID_DATA);
			Utils::writeLog("URL: Invalid input data for putting WBO " . $wboId . " of collection " . $collectionId . " for user " . $userId . ".");
			return false;
		}
                
        // Check if user has free space on limit
        $size = strlen(serialize($inputData)); // approximate the input data size
        if(!$this->checkUserQuota($userId, $size)) {
            return false;
        }

		// Get time to be updated in database and sent as header
		if (isset($inputData['modified'])) {
			$modifiedTime = $inputData['modified'];
		} else {
			$modifiedTime = Utils::getMozillaTimestamp();
		}

		$result = Storage::saveWBO($userId, $modifiedTime, $collectionId,
			$inputData->getInputArray());

		if ($result == false) {
			Utils::writeLog("Failed to save WBO " . $wboId . " of collection " . $collectionId . " for user " . $userId . ".");
			return false;
		}

		// Return the same modification time in payload and X-Weave-Timestamp header
		OutputData::write($modifiedTime, $modifiedTime);
	}

	/**
	* @brief Deletes the WBO with the given ID.
	*
	* HTTP request: DELETE https://server/pathname/version/username/storage/collection/id
	*
	* @param integer $userId
	* @param integer $collectionId
	* @param integer $wboId
	* @return bool true if success
	*/
	private function deleteWBO($userId, $collectionId, $wboId) {

		$result = Storage::deleteWBO($userId, $collectionId, $wboId);

		if($result == false) {
			Utils::writeLog("Failed to delete WBO " . $wboId . " of collection " . $collectionId . " for user " . $userId . ".");
			return false;
		}

		OutputData::write(Utils::getMozillaTimestamp());
		return true;
	}

	/**
	* @brief Deletes all records for the specified user.
	*
	* HTTP request: DELETE https://server/pathname/version/username/storage
	*
	* Will return a precondition error unless an X-Confirm-Delete header is included.
	*
	* All delete requests return the timestamp of the action.
	*
	* @param integer $userId The user whose records will be deleted.
	* @return bool True on success, false otherwise.
	*/
	private function deleteStorage($userId) {
		// Only continue if X-Confirm-Delete header is set
		if(!isset($_SERVER['HTTP_X_CONFIRM_DELETE'])) {
			Utils::writeLog("Did not send X_CONFIRM_DELETE header when trying to delete all records for user " . $userId . ".");
			return false;
		}

		$result = Storage::deleteStorage($userId);

		if($result == false) {
			Utils::writeLog("Failed to delete all records for user " . $userId . ".");
			return false;
		}

		OutputData::write(Utils::getMozillaTimestamp());
		return true;
	}

	/**
	* @brief Casts result rows to the correct type.
	*
	* Some implementations (e.g. PHP 5.3 in combination with MySQL 5.5) don't return the
	* correct type in JSON. To fix this we explicitly cast the values that have been
	* returned by the database.
	*
	* Casts <code>modified</code> to float, <code>sortindex</code> to int.
	*
	* @param array $row Row returned from the database.
	* @return array Row with explicitly casted types.
	*/
	public static function forceTypeCasting($row) {
		// Return modified as float, not string
		if (isset($row['modified'])) {
			if (is_null($row['modified'])) {
				unset($row['modified']);
			} else {
				$row['modified'] = (float) $row['modified'];
			}
		}

		// Return sortindex as int, not string
		if (isset($row['sortindex'])) {
			if (is_null($row['sortindex'])) {
				unset($row['sortindex']);
			} else {
				$row['sortindex'] = (int) $row['sortindex'];
			}
		}

		return $row;
	}
}

/* vim: set ts=4 sw=4 tw=80 noet : */
