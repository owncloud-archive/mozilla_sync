<?php

/**
* Copyright (c) 2013 Stefan Kleeschulte
*
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
*
* The above copyright notice and this permission notice shall be included in
* all copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
* SOFTWARE.
*
* @author Stefan Kleeschulte <mail@smk.biz>
* @copyright Copyright (c) 2013, Stefan Kleeschulte
* @license http://opensource.org/licenses/MIT MIT
*/

namespace OCA\mozilla_sync;

/**
* Class to convert an email address to Mozilla Sync's hashed base32 user account
* representation.
*
* Extracted from https://github.com/skleeschulte/php-base32
*/
class Base32 {

    /**
	* @var string RFC 4648 base32 alphabet
	* @link http://tools.ietf.org/html/rfc4648#page-9
	*/
	private static $_commonAlphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567=';

	/**
	* Returns the number of bytes in the given string.
	*
	* @param string $byteString The string whose bytes to count.
	* @return int Number of bytes in given string.
	*/
	private static function _byteCount($byteString) {
        if (function_exists('mb_strlen')) {
            return mb_strlen($byteString, '8bit');
        }
        return strlen($byteString);
    }

    /**
	* Base 32 encodes the given byte-string using the given alphabet.
	*
	* @param string $byteStr String containing the bytes to be encoded.
	* @param string $alphabet The alphabet to be used for encoding.
	* @param bool $pad If true, the encoded string is padded using the padding
	* character specified in the given alphabet to have a length which
	* is evenly divisible by 8.
	* @return string The encoded string.
	* @throws InvalidArgumentException
	*/
    private static function _encodeByteStr($byteStr, $alphabet, $pad) {
        // Check if argument is a string.
        if (!is_string($byteStr)) {
            $msg = 'Supplied argument 1 is not a string.';
            throw new InvalidArgumentException($msg, self::E_NO_STRING);
        }

        // Get byte count.
        $byteCount = self::_byteCount($byteStr);

        // Make byte count divisible by 5.
        $remainder = $byteCount % 5;
        $fillbyteCount = ($remainder) ? 5 - $remainder : 0;
        if ($fillbyteCount > 0)
            $byteStr .= str_repeat(chr(0), $fillbyteCount);

        // Iterate over blocks of 5 bytes and build encoded string.
        $encodedStr = '';
        for ($i = 0; $i < ($byteCount + $fillbyteCount); $i = $i + 5) {
            // Convert chars to bytes.
            $byte1 = ord($byteStr[$i]);
            $byte2 = ord($byteStr[$i + 1]);
            $byte3 = ord($byteStr[$i + 2]);
            $byte4 = ord($byteStr[$i + 3]);
            $byte5 = ord($byteStr[$i + 4]);
            // Read first 5 bit group.
            $bitGroup = $byte1 >> 3;
            $encodedStr .= $alphabet[$bitGroup];
            // Read second 5 bit group.
            $bitGroup = ($byte1 & ~(31 << 3)) << 2 | $byte2 >> 6;
            $encodedStr .= $alphabet[$bitGroup];
            // Read third 5 bit group.
            $bitGroup = $byte2 >> 1 & ~(3 << 5);
            $encodedStr .= $alphabet[$bitGroup];
            // Read fourth 5 bit group.
            $bitGroup = ($byte2 & 1) << 4 | $byte3 >> 4;
            $encodedStr .= $alphabet[$bitGroup];
            // Read fifth 5 bit group.
            $bitGroup = ($byte3 & ~(15 << 4)) << 1 | $byte4 >> 7;
            $encodedStr .= $alphabet[$bitGroup];
            // Read sixth 5 bit group.
            $bitGroup = $byte4 >> 2 & ~(1 << 5);
            $encodedStr .= $alphabet[$bitGroup];
            // Read seventh 5 bit group.
            $bitGroup = ($byte4 & ~(63 << 2)) << 3 | $byte5 >> 5;
            $encodedStr .= $alphabet[$bitGroup];
            // Read eighth 5 bit group.
            $bitGroup = $byte5 & ~(7 << 5);
            $encodedStr .= $alphabet[$bitGroup];
        }

        // Replace fillbit characters at the end of the encoded string.
        $encodedStrLen = ($byteCount + $fillbyteCount) * 8 / 5;
        $fillbitCharCount = (int) ($fillbyteCount * 8 / 5);
        $encodedStr = substr($encodedStr, 0, $encodedStrLen - $fillbitCharCount);
        if ($pad)
            $encodedStr .= str_repeat($alphabet[32], $fillbitCharCount);

        // Return encoded string.
        return $encodedStr;
    }

    /**
	* Encodes the bytes in the given byte-string according to the base 32
	* encoding described in RFC 4648 p. 8f
	* (http://tools.ietf.org/html/rfc4648#page-8).
	*
	* @param string $byteStr String containing the bytes to be encoded.
	* @param bool $omitPadding If true, no padding characters are appended to
	* the encoded string. Defaults to false.
	* @return string The encoded string.
	*/
    public static function encodeByteStr($byteStr, $omitPadding = false) {
        return self::_encodeByteStr($byteStr, self::$_commonAlphabet, !$omitPadding);
    }
}

/* vim: set ts=4 sw=4 tw=80 noet : */
