<?php

/**
* ownCloud
*
* @author Michal Jaskurzynski
* @copyright 2012 Michal Jaskurzynski mjaskurzynski@gmail.com
*
*/

namespace OCA_mozilla_sync;

class Storage
{
	/**
	* @brief Get index of collection, if collection doesn't exist it will be created
	*
	* @param string $collectionName
	*/
	static public function collectionNameToIndex($userId, $collectionName) {
		$query = \OCP\DB::prepare( 'SELECT `id` FROM `*PREFIX*mozilla_sync_collections` WHERE `userid`=? AND `name`=?');
		$result = $query->execute( array($userId, $collectionName) );

		$row=$result->fetchRow();
		if($row) {
			return $row['id'];
		}

		//
		// No collection found
		//
		$query = \OCP\DB::prepare( 'INSERT INTO `*PREFIX*mozilla_sync_collections` (`userid`, `name`) VALUES (?,?)' );
		$result = $query->execute( array($userId, $collectionName) );

		if($result == false) {
			return false;
		}

		return \OCP\DB::insertid('*PREFIX*mozilla_sync_collections');
	}

	/**
	* @brief Delete old wbo
	*/
	static public function deleteOldWbo() {
		$query = \OCP\DB::prepare( 'DELETE FROM `*PREFIX*mozilla_sync_wbo` WHERE `ttl` > 0 AND (`modified` + `ttl`) < CAST( ? AS DECIMAL(15,2))' );
		$result = $query->execute( array(Utils::getMozillaTimestamp()) );

		if($result == false) {
			return false;
		}

		return true;
	}

	/**
	* @brief Save Weave Basic Object (update previous one)
	*
	* @param int $collectionId
	* @param float $modifiedTime
	* @param array $wboArray
	*/
	static public function saveWBO($userId, $modifiedTime, $collectionId, $wboArray) {
		if(!array_key_exists('id', $wboArray)) {
			return false;
		}

		$query = \OCP\DB::prepare( 'SELECT 1 FROM `*PREFIX*mozilla_sync_wbo` WHERE `collectionid` = ? AND `name` = ?' );
		$result = $query->execute( array($collectionId, $wboArray['id']) );

		// No wbo found, add new wbo
		if($result->fetchRow() == false) {
			return self::insertWBO($userId, $modifiedTime, $collectionId, $wboArray);
		}
		else{
			return self::updateWBO($userId, $modifiedTime, $collectionId, $wboArray);
		}
	}

	/**
	* @brief Delete Wbo
	*
	* @param integer $userId
	* @param integer $collectionId
	* @param integer $wboId
	* @return boolean
	*/
	static public function deleteWBO($userId, $collectionId, $wboId) {
		$query = \OCP\DB::prepare( 'DELETE FROM `*PREFIX*mozilla_sync_wbo` WHERE `collectionid`=? AND `name`=?' );
		$result = $query->execute( array($collectionId, $wboId) );

		if($result == false) {
			return false;
		}

		return true;
	}

	static private function insertWBO($userId, $modifiedTime, $collectionId, $wboArray) {

		$queryString = 'INSERT INTO `*PREFIX*mozilla_sync_wbo`(`collectionid`, `name`, `modified`, `payload`';
		$queryArgs = array($collectionId, $wboArray['id'], $modifiedTime, $wboArray['payload']);

		$valuesString = 'VALUES (?,?,?,?';

		$wboArgs = array('`sortindex`', '`ttl`', '`parentid`', '`predecessorid`');
		foreach($wboArgs as $value)
		{
			if(array_key_exists($value, $wboArray)) {
				$queryString .= ', ' .$value;
				$queryArgs[] = $wboArray[$value];
				$valuesString .= ',?';
			}
		}
		$valuesString .= ')';
		$queryString .= ') ' .$valuesString;

		$query = \OCP\DB::prepare($queryString);
		$result = $query->execute($queryArgs);

		if($result == false) {
			return false;
		}

		return true;
	}

	static private function updateWBO($userId, $modifiedTime, $collectionId, $wboArray) {

		$queryString= 'UPDATE `*PREFIX*mozilla_sync_wbo` SET `modified`=?';
		$queryArgs = array($modifiedTime);

		$wboArgs = array('sortindex', 'ttl', 'parentid', 'predecessorid', 'payload');
		foreach($wboArgs as $value)
		{
			if(array_key_exists($value, $wboArray)) {
				$queryString .= ', ' .$value. '=?';
				$queryArgs[] = $wboArray[$value];
			}
		}
		$queryString .= ' WHERE `collectionid`=? AND `name`=?';
		array_push($queryArgs, $collectionId, $wboArray['id']);

		$query = \OCP\DB::prepare($queryString);
		$result = $query->execute($queryArgs);

		if($result == false) {
			return false;
		}

		return true;
	}

	/**
	* @brief Delete user storage
	*
	* @param integer $userId
	* @return boolean
	*/
	static public function deleteStorage($userId) {
		$query = \OCP\DB::prepare( 'DELETE FROM `*PREFIX*mozilla_sync_wbo` WHERE `collectionid` IN (SELECT `id` FROM `*PREFIX*mozilla_sync_collections` WHERE `userid` = ?)' );
		$result = $query->execute( array($userId) );

		if($result == false) {
			return false;
		}


		$query = \OCP\DB::prepare( 'DELETE FROM `*PREFIX*mozilla_sync_collections` WHERE `userid` = ?' );
		$result = $query->execute( array($userId) );

		if($result == false) {
			return false;
		}

		return true;
	}

	/**
	* @brief Convert modifiers array to sql string
	*
	*/
	static public function modifiersToString(&$modifiers, &$queryArgs, &$limit, &$offset) {
		$whereString = '';

		//
		// ids
		//
		if(isset($modifiers['ids'])) {

			if(gettype($modifiers['ids']) == 'array') {
				$first = true;
				$whereString .= ' AND (';
				foreach($modifiers['ids'] as $value) {
					if($first) {
						$first = false;
					}
					else{
						$whereString .= ' OR ';
					}
					$whereString .= '`name` = ?';
					$queryArgs[] = $value;
				}
				$whereString .= ')';
			}
			else{
				$whereString .= ' AND `name` = ?';
				$queryArgs[] = $modifiers['ids'];
			}
		}

		//
		// predecessorid
		//
		if(isset($modifiers['predecessorid'])) {
			$whereString .= ' AND `predecessorid` = ?';
			$queryArgs[] = $modifiers['predecessorid'];
		}

		//
		// parentid
		//
		if(isset($modifiers['parentid'])) {
			$whereString .= ' AND `parentid` = ?';
			$queryArgs[] = $modifiers['parentid'];
		}

		//
		// time modifiers
		//
		if(isset($modifiers['older'])) {
			$whereString .= ' AND `modified` <= CAST( ? AS DECIMAL(15,2))';
			$queryArgs[] = $modifiers['older'];
		}
		else if(isset($modifiers['newer'])) {
			$whereString .= ' AND `modified` >= CAST( ? AS DECIMAL(15,2))';
			$queryArgs[] = $modifiers['newer'];
		}
		else if(isset($modifiers['index_above'])) {
			$whereString .= ' AND `sortindex` >= ?';
			$queryArgs[] = $modifiers['index_above'];
		}
		else if(isset($modifiers['index_below'])) {
			$whereString .= ' AND `sortindex` <= ?';
			$queryArgs[] = $modifiers['index_below'];
		}

		//
		// sort
		//
		if(isset($modifiers['sort'])) {
			if($modifiers['sort'] == 'oldest') {
				$whereString .= ' ORDER BY `modified` ASC';
			}
			else if($modifiers['sort'] == 'newest') {
				$whereString .= ' ORDER BY `modified` DESC';
			}
			else if($modifiers['sort'] == 'index') {
				$whereString .= ' ORDER BY `sortindex` DESC';
			}
		}

		//
		// limit and offset
		//
		if(isset($modifiers['limit'])) {
			$limit = intval($modifiers['limit']);
		}
		if(isset($modifiers['offset'])) {
			$offset = intval($modifiers['offset']);
		}

		return $whereString;
	}

	/**
	* @brief Gets the time of the last modification for the logged in user.
	*
	* @return string Last modification time formatted according to ISO 8601
	*/
	public static function getLastModifiedTime() {
		$modifieds = self::getCollectionModifiedTimes();

		if ($modifieds === false) {
			return false;
		}

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
	* @param string $userId User ID whose collections are queried, currently logged in user by default.
	* @return mixed Array of collection => modified.
	*/
	public static function getCollectionModifiedTimes($userId = NULL) {

		// Get logged in user by default
		if (is_null($userId)) {
			$userId = User::userNameToUserId(\OCP\User::getUser());
		}

		if ($userId === false) {
			return false;
		}

		$query = \OCP\DB::prepare( 'SELECT `name`,
																		(SELECT max(`modified`) FROM `*PREFIX*mozilla_sync_wbo`
																			WHERE `*PREFIX*mozilla_sync_wbo`.`collectionid` = `*PREFIX*mozilla_sync_collections`.`id`
																		) AS `modified`
															FROM `*PREFIX*mozilla_sync_collections` WHERE `userid` = ?');
		$result = $query->execute( array($userId) );

		if($result == false) {
			return false;
		}

		$resultArray = array();

		while (($row = $result->fetchRow())) {

			// Skip empty collections
			if($row['modified'] == null) {
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
	* @return int Size of stored data in KB.
	*/
	public static function getSyncSize() {
		$sizes = self::getCollectionSizes();

		if ($sizes === false) {
			return false;
		}

		$totalSize = 0;

		foreach ($sizes as $size) {
			$totalSize = $totalSize + ((int) $size);
		}

		return $totalSize;
	}
	
	/**
	* @brief Get the size of each collection for a user.
	*
	* @param string $userId The user ID whose collection sizes are returned, the logged in user by default.
	* @return mixed Array of collection => size in KB for the specified user.
	*/
	public static function getCollectionSizes($userId = NULL) {

		// Get logged in user by default
		if (is_null($userId)) {
			$userId = User::userNameToUserId(\OCP\User::getUser());
		}

		if ($userId === false) {
			return false;
		}

		$query = \OCP\DB::prepare( 'SELECT name,
									(SELECT SUM(CHAR_LENGTH(payload)) FROM *PREFIX*mozilla_sync_wbo
									WHERE *PREFIX*mozilla_sync_wbo.collectionid = *PREFIX*mozilla_sync_collections.id
									) as size
									FROM *PREFIX*mozilla_sync_collections WHERE userid = ?');
		$result = $query->execute( array($userId) );

		if($result == false) {
			return false;
		}

		$resultArray = array();

		while (($row = $result->fetchRow())) {

			// Skip empty collections
			if($row['size'] == null) {
				continue;
			}

			$key = $row['name'];
			// Convert bytes to KB
			$value = ((float) $row['size'])/1000.0;

			$resultArray[$key] = $value;
		}

		return $resultArray;
	}
	
	/**
	* @brief Gets the number of sync clients for a user.
	*
	* @param string $userId The user ID whose number of clients are returned, the logged in user by default.
	* @return int The number of clients associated with the specified user.
	*/
	public static function getNumClients($userId = NULL) {

		// Get logged in user by default
		if (is_null($userId)) {
			$userId = User::userNameToUserId(\OCP\User::getUser());
		}

		if ($userId === false) {
			return false;
		}

		$query = \OCP\DB::prepare('SELECT 1 FROM `*PREFIX*mozilla_sync_wbo` WHERE `collectionid` = 
									(SELECT `id` FROM `*PREFIX*mozilla_sync_collections` WHERE `name` = ? AND `userid` = ?)');
		$result = $query->execute(array('clients', $userId));

		if ($result === false) {
			return false;
		}
	    
		return ((int) $result->numRows());
	}
}

/* vim: set ts=4 sw=4 tw=80 noet : */
