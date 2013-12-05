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

class Storage
{
	/**
	* @brief Get index of collection, if collection doesn't exist it will be
	* created.
	*
	* @param string $syncId The Sync user that the collection is belonging to.
	* @param string $collectionName The name of the collection.
	*/
	static public function collectionNameToIndex($syncId, $collectionName) {
		$query = \OCP\DB::prepare('SELECT `id` FROM
			`*PREFIX*mozilla_sync_collections` WHERE `userid` = ? AND
			`name` = ?');
		$result = $query->execute(array($syncId, $collectionName));

		// Collection found, return its ID
		$row = $result->fetchRow();
		if ($row) {
			return $row['id'];
		}

		// No collection found, create new collection
		$query = \OCP\DB::prepare('INSERT INTO
			`*PREFIX*mozilla_sync_collections` (`userid`, `name`) VALUES
			(?, ?)');
		$result = $query->execute(array($syncId, $collectionName));

		// Creation of collection failed
		if ($result == false) {
			Utils::writeLog("DB: Could not create collection " . $collectionName . ".");
			return false;
		}

		return \OCP\DB::insertid('*PREFIX*mozilla_sync_collections');
	}

	/**
	* @brief Delete old WBO.
	*
	* @return bool True when old WBO were deleted, false otherwise.
	*/
	static public function deleteOldWbo() {
		$query = \OCP\DB::prepare('DELETE FROM `*PREFIX*mozilla_sync_wbo` WHERE
			`ttl` > 0 AND (`modified` + `ttl`) < CAST(? AS DECIMAL(15,2))');
		$result = $query->execute(array(Utils::getMozillaTimestamp()));

		if ($result == false) {
			// Also returns false when no old WBO was found -> don't Utils::writeLog() as it would spam the log
			return false;
		}

		return true;
	}

	/**
	* @brief Store new Weave Basic Object or update previous one.
	*
	* @param string $syncId The Sync user whose WBO is updated.
	* @param int $collectionId The collection ID whose WBO is updated.
	* @param float $modifiedTime The modification time that will be saved for
	* the updated WBO.
	* @param array $wboArray WBO which will be updated. Passed as JSON array.
	* @return boolean True on success, false otherwise.
	*/
	static public function saveWBO($syncId, $modifiedTime, $collectionId, $wboArray) {
		if (!array_key_exists('id', $wboArray)) {
			Utils::writeLog("Failed to save WBO as no ID was present.");
			return false;
		}

		$query = \OCP\DB::prepare('SELECT 1 FROM `*PREFIX*mozilla_sync_wbo`
			WHERE `collectionid` = ? AND `name` = ?');
		$result = $query->execute(array($collectionId, $wboArray['id']));

		// No WBO found, add a new one
		if ($result->fetchRow() == false) {
			return self::insertWBO($syncId, $modifiedTime, $collectionId, $wboArray);
		} else {
			return self::updateWBO($syncId, $modifiedTime, $collectionId, $wboArray);
		}
	}

	/**
	* @brief Delete a WBO.
	*
	* @param integer $syncId The Sync user whose WBO will be deleted.
	* @param integer $collectionId Collection ID whose WBO will be deleted.
	* @param integer $wboId WBO's ID which will be deleted.
	* @return boolean True on success, false otherwise.
	*/
	static public function deleteWBO($syncId, $collectionId, $wboId) {
		$query = \OCP\DB::prepare('DELETE FROM `*PREFIX*mozilla_sync_wbo`
			WHERE `collectionid` = ? AND `name` = ?');
		$result = $query->execute(array($collectionId, $wboId));

		if ($result == false) {
			Utils::writeLog("DB: Could not delete WBO " . $wboId . ".", \OCP\Util::INFO);
			return false;
		}

		return true;
	}

	/**
	* @brief Inserts a new WBO into the database.
	*
	* @param integer $syncId The Sync user whose WBO will be inserted.
	* @param integer $collectionId Collection ID whose WBO will be inserted.
	* @param integer $wboId WBO's ID which will be inserted.
	* @param array $wboArray WBO as JSON array which will be inserted into the
	* database.
	* @return True on success, false otherwise.
	*/
	static private function insertWBO($syncId, $modifiedTime, $collectionId, $wboArray) {

		$queryString = 'INSERT INTO `*PREFIX*mozilla_sync_wbo` (`collectionid`, `name`, `modified`, `payload`';
		$queryArgs = array($collectionId, $wboArray['id'], $modifiedTime, $wboArray['payload']);

		$valuesString = 'VALUES (?,?,?,?';

		// Add values from array to insert query statement
		$wboArgs = array('`sortindex`', '`ttl`', '`parentid`', '`predecessorid`');
		foreach ($wboArgs as $value)	{
			if (array_key_exists($value, $wboArray)) {
				$queryString .= ', ' .$value;
				$queryArgs[] = $wboArray[$value];
				$valuesString .= ',?';
			}
		}
		$valuesString .= ')';
		$queryString .= ') ' .$valuesString;

		$query = \OCP\DB::prepare($queryString);
		$result = $query->execute($queryArgs);

		if ($result == false) {
			Utils::writeLog("DB: Could not insert WBO for user " . $syncId . " in collection " . $collectionId . ".");
			return false;
		}

		return true;
	}

	/**
	* @brief Updates an already existing WBO.
	*
	* @param integer $syncId The Sync user whose WBO will be updated.
	* @param number $modifiedTime Updated last modification time.
	* @param integer $collectionId Collection ID whose WBO will be updated.
	* @param array $wboArray WBO as JSON array which will be updated in the
	* database.
	* @return True on success, false otherwise.
	*/
	static private function updateWBO($syncId, $modifiedTime, $collectionId, $wboArray) {

		$queryString= 'UPDATE `*PREFIX*mozilla_sync_wbo` SET `modified` = ?';
		$queryArgs = array($modifiedTime);

		// Add values from array to update query statement
		$wboArgs = array('sortindex', 'ttl', 'parentid', 'predecessorid', 'payload');
		foreach ($wboArgs as $value) {
			if (array_key_exists($value, $wboArray)) {
				$queryString .= ', ' .$value. '=?';
				$queryArgs[] = $wboArray[$value];
			}
		}
		$queryString .= ' WHERE `collectionid` = ? AND `name` = ?';
		array_push($queryArgs, $collectionId, $wboArray['id']);

		$query = \OCP\DB::prepare($queryString);
		$result = $query->execute($queryArgs);

		if ($result == false) {
			Utils::writeLog("DB: Could not update WBO for user " . $syncId . " in collection " . $collectionId . ".");
			return false;
		}

		return true;
	}

	/**
	* @brief Delete complete storage for a user.
	*
	* @param integer $syncId The user's Sync ID whose storage will be deleted.
	* @return boolean True on success, false otherwise.
	*/
	static public function deleteStorage($syncId) {
		// Delete all WBO for this user
		$query = \OCP\DB::prepare('DELETE FROM `*PREFIX*mozilla_sync_wbo` WHERE
			`collectionid` IN (SELECT `id` FROM `*PREFIX*mozilla_sync_collections`
			WHERE `userid` = ?)');
		$result = $query->execute(array($syncId));

		if ($result == false) {
			Utils::writeLog("DB: Could not delete storage for user " . $syncId . ".");
			return false;
		}

		// Delete all collections for this user
		$query = \OCP\DB::prepare('DELETE FROM `*PREFIX*mozilla_sync_collections`
			WHERE `userid` = ?');
		$result = $query->execute(array($syncId));

		if ($result == false) {
			Utils::writeLog("DB: Could not delete collections for user " . $syncId . ".");
			return false;
		}

		return true;
	}

	/**
	* @brief Convert modifiers array to SQL string, query arguments, limit and
	* offset.
	*
	* @param array &$modifiers Array containing the modifiers for the SQL
	* statement.
	* @param array &$queryArgs Array with the query arguments.
	* @param int &$limit The number of elements which will be retrieved.
	* @param int &$offset Elements starting from this offset will be retrieved.
	* @return string Returns the WHERE SQL string.
	*/
	static public function modifiersToString(&$modifiers, &$queryArgs, &$limit, &$offset) {
		$whereString = '';

		// IDs
		if (isset($modifiers['ids'])) {
			if (gettype($modifiers['ids']) == 'array') {
				$first = true;
				$whereString .= ' AND (';
				foreach ($modifiers['ids'] as $value) {
					if ($first) {
						$first = false;
					} else {
						$whereString .= ' OR ';
					}
					$whereString .= '`name` = ?';
					$queryArgs[] = $value;
				}
				$whereString .= ')';
			} else {
				$whereString .= ' AND `name` = ?';
				$queryArgs[] = $modifiers['ids'];
			}
		}

		// Predecessor ID
		if (isset($modifiers['predecessorid'])) {
			$whereString .= ' AND `predecessorid` = ?';
			$queryArgs[] = $modifiers['predecessorid'];
		}

		// Parent ID
		if (isset($modifiers['parentid'])) {
			$whereString .= ' AND `parentid` = ?';
			$queryArgs[] = $modifiers['parentid'];
		}

		// Time modifiers
		if (isset($modifiers['older'])) {
			$whereString .= ' AND `modified` <= CAST( ? AS DECIMAL(15,2))';
			$queryArgs[] = $modifiers['older'];
		} else if (isset($modifiers['newer'])) {
			$whereString .= ' AND `modified` >= CAST( ? AS DECIMAL(15,2))';
			$queryArgs[] = $modifiers['newer'];
		} else if (isset($modifiers['index_above'])) {
			$whereString .= ' AND `sortindex` >= ?';
			$queryArgs[] = $modifiers['index_above'];
		} else if (isset($modifiers['index_below'])) {
			$whereString .= ' AND `sortindex` <= ?';
			$queryArgs[] = $modifiers['index_below'];
		}

		// Sort
		if (isset($modifiers['sort'])) {
			if ($modifiers['sort'] == 'oldest') {
				$whereString .= ' ORDER BY `modified` ASC';
			} else if ($modifiers['sort'] == 'newest') {
				$whereString .= ' ORDER BY `modified` DESC';
			} else if ($modifiers['sort'] == 'index') {
				$whereString .= ' ORDER BY `sortindex` DESC';
			}
		}

		// Limit
		if (isset($modifiers['limit'])) {
			$limit = intval($modifiers['limit']);
		}

		// Offset
		if (isset($modifiers['offset'])) {
			$offset = intval($modifiers['offset']);
		}

		return $whereString;
	}

	/**
	* @brief Gets the time of the last modification for the logged in user.
	*
	* @return string Last modification time formatted according to ISO 8601.
	*/
	public static function getLastModifiedTime() {
		// Get collections with all modification times
		$modifieds = self::getCollectionModifiedTimes();

		if ($modifieds === false) {
			return false;
		}

		// Iterate through collections to find last modified record
		$lastMod = false;

		foreach ($modifieds as $modified) {
			$curr = (int) (substr($modified, 0, -3));
			if ($lastMod === false || $curr > $lastMod) {
				$lastMod = $curr;
			}
		}

		return date(DATE_ISO8601, $lastMod);
	}

	/**
	* @brief Get the last modification times for all collections of a user.
	*
	* @param string $syncId The Sync user ID whose collections are queried, currently logged in user by default.
	* @return mixed Array of collection => modified.
	*/
	public static function getCollectionModifiedTimes($syncId = NULL) {
		// Get logged in user by default
		if (is_null($syncId)) {
			$syncId = User::userNameToSyncId(\OCP\User::getUser());
		}

		if ($syncId === false) {
			Utils::writeLog("Failed to get user ID before getting the collection modified times.");
			return false;
		}

		$query = \OCP\DB::prepare('SELECT `name`, (SELECT max(`modified`) FROM
			`*PREFIX*mozilla_sync_wbo` WHERE
			`*PREFIX*mozilla_sync_wbo`.`collectionid` =
			`*PREFIX*mozilla_sync_collections`.`id`) AS `modified` FROM
			`*PREFIX*mozilla_sync_collections` WHERE `userid` = ?');
		$result = $query->execute(array($syncId));

		if ($result == false) {
			Utils::writeLog("DB: Could not get info collections for user " .
			$syncId . ".");
			return false;
		}

		$resultArray = array();

		while (($row = $result->fetchRow())) {

			// Skip empty collections
			if ($row['modified'] == null) {
				continue;
			}

			// Cast returned values to the correct type
			$row = StorageService::forceTypeCasting($row);

			$key = $row['name'];
			$value = $row['modified'];

			$resultArray[$key] = $value;
		}

		return $resultArray;
	}

	/**
	* @brief Get the total size of stored data for the logged in user.
	*
	* @return int Size of stored data in kB.
	*/
	public static function getSyncSize() {
		// Get all collections with their sizes
		$sizes = self::getCollectionSizes();

		if ($sizes === false) {
			return false;
		}

		// Iterate through collections and add sizes
		$totalSize = 0;

		foreach ($sizes as $size) {
			$totalSize = $totalSize + ((int) $size);
		}

		return $totalSize;
	}

	/**
	* @brief Get the size of each collection for a user.
	*
	* Returns the size of each collection for a specific user in kB. For SQLite
	*	the number of thousand characters are returned, since there is no byte length
	*	function for SQLite databases.
	*
	* @param string $syncId The Sync user whose collection sizes are returned,
	* the logged in user by default.
	* @return mixed Array of collection => size in kB for the specified user.
	*/
	public static function getCollectionSizes($syncId = NULL) {

		// Get logged in user by default
		if (is_null($syncId)) {
			$syncId = User::userNameToSyncId(\OCP\User::getUser());
		}

		if ($syncId === false) {
			Utils::writeLog("Failed to get user ID before getting the collection sizes.");
			return false;
		}

		$query = \OCP\DB::prepare('SELECT name, (SELECT SUM(LENGTH(payload))
			FROM *PREFIX*mozilla_sync_wbo WHERE
			*PREFIX*mozilla_sync_wbo.collectionid =
			*PREFIX*mozilla_sync_collections.id) as size FROM
			*PREFIX*mozilla_sync_collections WHERE userid = ?');
		$result = $query->execute(array($syncId));

		if ($result == false) {
			Utils::writeLog("DB: Could not get info collection usage for user "
			. $syncId . ".");
			return false;
		}

		$resultArray = array();

		while (($row = $result->fetchRow())) {

			// Skip empty collections
			if ($row['size'] == null) {
				continue;
			}

			$key = $row['name'];
			// Convert bytes to kB
			$value = ((float) $row['size'])/1000.0;

			$resultArray[$key] = $value;
		}

		return $resultArray;
	}

	/**
	* @brief Gets the number of sync clients for a user.
	*
	* @param string $syncId The Sync user whose number of clients are returned, the logged in user by default.
	* @return int The number of clients associated with the specified user.
	*/
	public static function getNumClients($syncId = NULL) {

		// Get logged in user by default
		if (is_null($syncId)) {
			$syncId = User::userNameToSyncId(\OCP\User::getUser());
		}

		if ($syncId === false) {
			Utils::writeLog("Failed to get user ID before getting the number of clients.");
			return false;
		}

		$query = \OCP\DB::prepare('SELECT 1 FROM `*PREFIX*mozilla_sync_wbo`
			WHERE `collectionid` = (SELECT `id` FROM
			`*PREFIX*mozilla_sync_collections` WHERE `name` = ? AND `userid` = ?)');
		$result = $query->execute(array('clients', $syncId));

		if ($result === false) {
			Utils::writeLog("DB: Could not get number of clients for user " .
			$syncId . ".");
			return false;
		}

		return ((int) $result->numRows());
	}
}

/* vim: set ts=4 sw=4 tw=80 noet : */
