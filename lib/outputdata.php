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

/**
* Class for writing output for mozilla sync service client
*
* It can be used to write simple output like:
*  - '0'
*  - '1'
*  - 'success'
*  Or can be used to write json formated output. In this case argument of function write should be an array.
*
*/
class OutputData
{
	// Three different types of output formats
	const NewlinesFormat        = 0;
	const LengthFormat          = 1;
	const JsonFormat            = 2;

	/**
	* @brief Function for writing output.
	*
	* Arrays will be encoded to JSON or other formats depending on the
	* Content-Type header sent by the client.
	*
	* @param any $output The output sent back to the client.
	* @param number $modifiedTime Modified time which will be sent back to the
	* client as a HTTP header. By default the current time is sent.
	*/
	static public function write($output, $modifiedTime = null) {

		// If no modified time is set get a timestamp now, then send the header
		Utils::sendMozillaTimestampHeader($modifiedTime);

		// Write simple output
		if (gettype($output) != 'array') {
			self::writeOutput($output);
		}
		// Write encoded output
		else {
			switch(OutputData::getOutputFormat()) {
				case self::NewlinesFormat:
					self::writeNewlinesFormat($output); break;
				case self::LengthFormat:
					self::writeLengthFormat($output); break;
				case self::JsonFormat:
					self::writeJsonFormat($output); break;
			}
		}
	}

	/**
	* @brief Get output format.
	*
	*  Two alternate output formats are available for multiple record GET requests.
	*  They are triggered by the presence of the appropriate format in the
	*  Accept header (with application/whoisi taking precedence):
	*
	*  - application/whoisi:     each record consists of a 32-bit integer,
	*                            defining the length of the record, followed by
	*							 the json record for a WBO
	*  - application/newlines:   each record is a separate json object on its own line.
	*                            Newlines in the body of the json object are replaced by ‘u000a’
	*
	* @return int Returns the int representing NewlinesFormat, LengthFormat or
	* JsonFormat.
	*/
	static private function getOutputFormat() {
		if (isset($_SERVER['HTTP_ACCEPT']) &&
				stristr($_SERVER['HTTP_ACCEPT'], 'application/newlines')) {
			return self::NewlinesFormat;
		} else if (isset($_SERVER['HTTP_ACCEPT']) &&
				stristr($_SERVER['HTTP_ACCEPT'], 'application/whoisi')) {
			return self::LengthFormat;
		} else {
			// JSON format is the default
			return self::JsonFormat;
		}
	}

	/**
	* @brief Write output in JSON format.
	*
	* @param mixed $outputArray The array that will be written in JSON format.
	*/
	static private function writeJsonFormat($outputArray) {
		header('Content-Type: application/json');

		self::writeOutput(json_encode($outputArray));
	}

	/**
	* @brief Write output in Newlines format.
	*
	* @param mixed $outputArray The array that will be written in Newlines
	* format.
	*
	*/
	static private function writeNewlinesFormat($outputArray) {
		header('Content-Type: application/newlines');

		$output = '';
		foreach ($outputArray as $value) {
			$output = $output . json_encode($value) . "\n";
		}

		self::writeOutput($output);
	}

	/**
	* @brief Write output in Length format.
	*
	* @param mixed $outputArray The array that will be written in Length format.
	*/
	static private function writeLengthFormat($outputArray) {
		header('Content-Type: application/whoisi');

		$output = '';
		foreach ($outputArray as $value) {
			$json_obj = json_encode($value);
			$json_len = strlen($json_obj);

			$output = $output . pack('N', $json_len) . $json_obj;
		}

		self::writeOutput($output);
	}

	/**
	* @brief Writes the previously converted output.
	*
	* @param string $outputString The output string to be written.
	*/
	static private function writeOutput($outputString) {
		header('Content-Length: ' . strlen($outputString));
		print $outputString;
	}
}

/* vim: set ts=4 sw=4 tw=80 noet : */
