<?php

/**
* ownCloud
*
* @author Michal Jaskurzynski
* @author Oliver Gasser
* @copyright 2012 Michal Jaskurzynski mjaskurzynski@gmail.com
*
*/

namespace OCA_mozilla_sync;

class Utils
{
	/**
	* @brief Writes debug output to the ownCloud log.
	*
	* @param string $output The string appended to ownCloud log.
	* @param int $level Log level of debug output. Default is \OCP\Util::ERROR.
	*/
	public static function writeLog($output, $level = \OCP\Util::ERROR) {
		// Prepend file name, line number, calling function to debug output
		$caller = debug_backtrace()[1];
		$output = basename($caller["file"]) . "#" . $caller["line"] . " " . $caller["function"] . "():  " . $output;

		\OCP\Util::writeLog("mozilla_sync", $output, $level);
	}

	/**
	* Mozilla sync status codes:
	*
	* 200 The request was processed successfully.
	*
	* 400 The request itself or the data supplied along with the request is invalid.
	*     The response contains a numeric code indicating the reason for why the request was rejected.
	*     See Response codes for a list of valid response codes.
	*
	* 401 The username and password are invalid on this node.
	*     This may either be caused by a node reassignment or by a password change.
	*     The client should check with the auth server whether the user’s node has changed.
	*     If it has changed, the current sync is to be aborted and should be retried against the new node.
	*     If the node hasn’t changed, the user’s password was changed.
	*
	* 404 The requested resource could not be found.
	*     This may be returned for GET and DELETE requests, for non-existent records and empty collections.
	*
	* 503 Indicates, in conjunction with the Retry-After header, that the server is undergoing maintenance.
	*     The client should not attempt another sync for the number of seconds specified in the header value.
	*     The response body may contain a JSON string describing the server’s status or error.
	*/
	const STATUS_OK                = 200;
	const STATUS_INVALID_DATA      = 400;
	const STATUS_INVALID_USER      = 401;
	const STATUS_NOT_FOUND         = 404;
	const STATUS_MAINTENANCE       = 503;

	static public $lastStatus      = self::STATUS_OK;
	static public $requestMethod   = 'GET';

	/**
	* @brief Change HTTP response code.
	*
	* @param integer $statusCode The new HTTP status code.
	*/
	public static function changeHttpStatus($statusCode) {

		$message = '';
		switch ($statusCode) {
			case 404: $message = 'Not Found'; break;
			case 400: $message = 'Bad Request'; break;
			case 500: $message = 'Internal Server Error'; break;
			case 503: $message = 'Service Unavailable'; break;
		}

		// Set status code and status message in HTTP header
		header('HTTP/1.0 ' . $statusCode . ' ' . $message);
	}

	/**
	* @brief Change HTTP response code and send additional Mozilla Sync status
	*	code.
	*
	* @param integer $httpStatusCode The HTTP status code to set in the response
	*	header.
	* @param integer $syncErrorCode The Sync error code that is written as a
	*	response.
	*/
	public static function sendError($httpStatusCode, $syncErrorCode) {
		self::changeHttpStatus($httpStatusCode);
		OutputData::write($syncErrorCode);
	}

	/**
	* @brief Gets the HTTP request method that was used by the client.
	*
	* @return string The HTTP request method used by the client.
	*/
	public static function getRequestMethod() {
		return $_SERVER['REQUEST_METHOD'];
	}

	/**
	* @brief Generate Mozilla Sync timestamp for time synchronization and send
	*	it in header.
	*
	* @param number $timestamp The timestamp to send to the client. By default
	*	the current time will be sent.
	*/
	public static function sendMozillaTimestampHeader($timestamp = null) {
		if (is_null($timestamp)) {
			$timestamp = self::getMozillaTimestamp();
		}
		header('X-Weave-Timestamp: ' . $timestamp);
	}

	/**
	* @brief Get current time in Mozilla Sync format.
	*
	* @return number The current Unix time with 2 digits after the
	*	comma.
	*/
	public static function getMozillaTimestamp() {
		return round(microtime(true),2);
	}

	/**
	* @brief Get the server address for the Mozilla Sync service.
	*
	* @return string Remote server address to access the Mozilla Sync service.
	*/
	public static function getServerAddress() {
		return \OCP\Util::linkToRemote('mozilla_sync');
	}

	/**
	* @brief Convert $_GET array to URL parser string.
	*
	* Modifiers are passsed to this script via separete fields, for example:
	* Array
	* (
	*    [service] => storageapi
	*    [url] => 1.1/12345/storage/history
	*    [full] => 1
	*    [sort] => index
	*    [limit] => 20
	* )
	*
	* There is need to convert to UrlParser input string:
	* 1.1/12345/storage/history?full=1&sort=index&limit=20
	*
	* @return string The URL converted from the $_GET array.
	*/
	public static function prepareUrl() {
		unset($_GET['url']);
		unset($_GET['service']);

		$modifiers = '';

		if (count($_GET) > 0) {
			$first = true;
			foreach ($_GET as $key => $value) {
				if ($first) {
					$modifiers .= '?';
					$first = false;
				} else {
					$modifiers .= '&';
				}
				$modifiers .= $key . '=' . $value;
			}
		}

		return $modifiers;
	}

	/**
	* @brief Gets the URL of the Sync request.
	*
	* @return string URL of the Sync request.
	*/
	public static function getSyncUrl() {
		$url = self::getUrl();
		if (self::getServiceType() === 'userapi') {
			$url = str_replace('/user/', '', $url);
		}

		return $url;
	}

	/**
	* @brief Gets the URL requested by the client.
	*
	* @return mixed URL requested by the Sync client on success, false
	*	otherwise.
	*/
	private static function getUrl() {
		$url = \OCP\Util::getRequestUri();
		$url = str_replace('//', '/', $url);

		$pos = strpos($url, 'mozilla_sync');
		if ($pos === false) {
			return false;
		}
		$pos += strlen('mozilla_sync');

		$url = substr($url, $pos);

		return $url;
	}

	/**
	* @brief Gets the type of the requested Sync service.
	*
	* @return string 'storageapi' or 'userapi'
	*/
	public static function getServiceType() {
		if (strpos(self::getUrl(), '/user/') === 0) {
			return 'userapi';
		} else {
			return 'storageapi';
		}
	}
}

/* vim: set ts=4 sw=4 tw=80 noet : */
