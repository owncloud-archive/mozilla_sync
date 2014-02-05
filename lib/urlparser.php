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
* Class for parsing Mozilla URL Semantics
*
* For example:
* 	<version>/<username>/<further instruction>
*
*/
class UrlParser {

	/**
	* Constructor, parse given url.
	*
	* @param string $url Mozilla storage URL, for example /1.0/username/storage/history
	*/
	public function __construct($url) {

		// Parser is valid at the begining
		$this->parseValidFlag = true;

		// Parse URL
		$components = parse_url($url);

		// For seriously malformed URLs false is returned
		if ($components === false) {
			$this->parseValidFlag = false;
			return;
		}

		// Get URL path
		$url = $components["path"];

		// Remove '/' from beginning and end
		$url = trim($url, '/');

		$urlArray = explode('/', $url);

		// There should be at least 2 arguments: version, username
		if (count($urlArray) < 2) {
			$this->parseValidFlag = false;
			Utils::writeLog("URL: Found only " . count($urlArray) . " arguments, but need at least 2 in URL "
				. Utils::getSyncUrl() . ": " . var_export($urlArray, true));
			return;
		}

		// Parse version
		$this->version = array_shift($urlArray);
		// Ignore CAPTCHA request
		if ($this->version === 'misc') {
			$this->parseValidFlag = false;
			return;
		} else if (($this->version != '1.0') &&
			($this->version != '1.1') &&
			($this->version != '2.0')) {
			$this->parseValidFlag = false;
			Utils::writeLog("URL: Illegal version " . $this->version . " found.");
			return;
		}

		// Parse sync hash
		$this->syncHash = array_shift($urlArray);

		// Parse commands
		$this->commandsArray = $urlArray;


		// Get URL params (everything after the '?')
		if (isset($components["query"])) {
			$params = $components["query"];
			$params = trim($params, '&');

			$this->params = explode('&', $params);
		} else {
			$this->params = null;
		}
	}

	/**
	* @brief Verifies whether the URL is valid.
	*
	* @return bool True if URL is valid, false otherwise.
	*/
	public function isValid() {
		return $this->parseValidFlag;
	}

	/**
	* @brief Return version of the service requested in the URL.
	*
	* @return string Version of the service requested in the URL.
	*/
	public function getVersion() {
		return $this->version;
	}

	/**
	* @brief Return Mozilla Sync user hash from the URL.
	*
	* @return string Sync hash from the URL.
	*/
	public function getSyncHash() {
		return $this->syncHash;
	}

	/**
	* @brief Return command by number, starting from 0.
	*
	* @param integer $commandNumber The number of the command that will be
	* returned, starting from 0.
	* @return string The command at the requested index.
	*/
	public function getCommand($commandNumber) {
		return $this->commandsArray[$commandNumber];
	}

	/**
	* @brief Return modifiers array, i.e. URL parameters.
	*
	* Example:
	*   tabs?full=1&ids=1,2,3
	*
	* @return array Modifiers for the corresponding command.
	*/
	public function getCommandModifiers() {

		$resultArray = array();

		// Return an empty array for no parameters
		if (is_null($this->params)) {
			return $resultArray;
		}

		// Iterate over all URL params
		foreach ($this->params as $value) {
			$tmpArray = explode('=', $value);
			if (count($tmpArray) != 2) {
				continue;
			}

			$key = $tmpArray[0];

			// Split argument list, important for IDs
			if (strpos($tmpArray[1], ',') === false) {
				$value = $tmpArray[1];
			} else {
				$value = explode(',', $tmpArray[1]);
			}

			$resultArray[$key] = $value;
		}

		return $resultArray;
	}

	/**
	* @brief Return command array.
	*
	* @return array Commands in URL.
	*/
	public function getCommands() {
		return $this->commandsArray;
	}

	/**
	* @brief Return number of commands.
	*
	* @return integer Number of commands in URL.
	*/
	public function commandCount() {
		return count($this->commandsArray);
	}

	/**
	* @brief Check if command string matches given pattern.
	*
	* @param string $pattern Pattern to mach command string against.
	* @return boolean True if command string matches the pattern, false
	* otherwise.
	*/
	public function commandMatch($pattern) {
		$commandString = implode('/', $this->commandsArray);
		return preg_match($pattern, $commandString);
	}

	/**
	* Flag for checking parsing result
	*/
	private $parseValidFlag;

	/**
	* Mozilla storage API version
	*/
	private $version;

	/**
	* Mozilla Sync user hash
	*/
	private $syncHash;

	/**
	* Further commands array
	*/
	private $commandsArray;
}

/* vim: set ts=4 sw=4 tw=80 noet : */
