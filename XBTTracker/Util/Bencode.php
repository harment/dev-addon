<?php
namespace Harment\XBTTracker\Util;

/**
 * Class to handle bencoding/decoding of torrent files
 * 
 * This utility provides encoding and decoding functionality for the BitTorrent bencode format
 * which is used in torrent files for metadata serialization.
 * 
 * @package Harment\XBTTracker\Util
 */
class Bencode
{
    /**
     * Decode a bencoded string
     *
     * @param string $str The bencoded data to decode
     * @return mixed The decoded data as PHP native types (arrays, integers, strings)
     * @throws \Exception When the input is invalid or malformed
     */
    public static function decode($str)
    {
        if (!is_string($str) || empty($str)) {
            throw new \InvalidArgumentException('Input must be a non-empty string');
        }
        
        $pos = 0;
        $result = self::bdecode($str, $pos);
        
        if ($pos !== strlen($str)) {
            throw new \Exception('Invalid bencoded data: unexpected data at end');
        }
        
        return $result;
    }
    
    /**
     * Encode data to bencoded format
     *
     * @param mixed $data The data to encode (arrays, integers, strings)
     * @return string The bencoded representation
     * @throws \Exception When encoding fails or data type is unsupported
     */
    public static function encode($data)
    {
        if (is_array($data)) {
            if (self::isAssoc($data)) {
                return self::encodeDict($data);
            } else {
                return self::encodeList($data);
            }
        } else if (is_int($data) || is_float($data)) {
            return self::encodeInteger((int)$data);
        } else if (is_string($data)) {
            return self::encodeString($data);
        } else if (is_bool($data)) {
            return self::encodeInteger($data ? 1 : 0);
        } else if (is_null($data)) {
            return self::encodeString('');
        } else {
            throw new \Exception('Unsupported data type for bencode encoding: ' . gettype($data));
        }
    }
    
    /**
     * Helper function for bdecode
     *
     * @param string $str The bencoded string
     * @param int &$pos Current position in the string (modified by reference)
     * @return mixed The decoded value
     * @throws \Exception When decoding fails due to invalid format
     */
    private static function bdecode($str, &$pos)
    {
        $strlen = strlen($str);
        if ($pos >= $strlen) {
            throw new \Exception('Unexpected end of data');
        }
        
        $char = $str[$pos];
        
        switch ($char) {
            case 'd': // Dictionary
                $pos++;
                $result = [];
                while ($pos < $strlen && $str[$pos] != 'e') {
                    $key = self::bdecode($str, $pos);
                    if ($key === null || !is_string($key)) {
                        throw new \Exception('Dictionary key must be a string');
                    }
                    
                    if ($pos >= $strlen) {
                        throw new \Exception('Unexpected end of data');
                    }
                    
                    $value = self::bdecode($str, $pos);
                    $result[$key] = $value;
                }
                
                if ($pos >= $strlen) {
                    throw new \Exception('Unterminated dictionary');
                }
                
                $pos++; // Skip the 'e'
                return $result;
                
            case 'l': // List
                $pos++;
                $result = [];
                while ($pos < $strlen && $str[$pos] != 'e') {
                    $value = self::bdecode($str, $pos);
                    $result[] = $value;
                }
                
                if ($pos >= $strlen) {
                    throw new \Exception('Unterminated list');
                }
                
                $pos++; // Skip the 'e'
                return $result;
                
            case 'i': // Integer
                $pos++;
                $numStr = '';
                $isNegative = false;
                
                // Check for negative sign
                if ($pos < $strlen && $str[$pos] == '-') {
                    $isNegative = true;
                    $pos++;
                }
                
                // First digit cannot be 0 unless the value is exactly 0
                if ($pos < $strlen && $str[$pos] == '0' && 
                    $pos + 1 < $strlen && $str[$pos + 1] != 'e' && !$isNegative) {
                    throw new \Exception('Invalid integer format (leading zero)');
                }
                
                while ($pos < $strlen && $str[$pos] != 'e') {
                    if (!ctype_digit($str[$pos])) {
                        throw new \Exception('Invalid integer format (non-digit character)');
                    }
                    $numStr .= $str[$pos];
                    $pos++;
                }
                
                if ($pos >= $strlen) {
                    throw new \Exception('Unterminated integer');
                }
                
                if ($isNegative && $numStr == '0') {
                    throw new \Exception('Invalid integer format (negative zero)');
                }
                
                $pos++; // Skip the 'e'
                return $isNegative ? -intval($numStr) : intval($numStr);
                
            default:
                // String
                if (ctype_digit($char)) {
                    $numLen = '';
                    while ($pos < $strlen && ctype_digit($str[$pos])) {
                        $numLen .= $str[$pos];
                        $pos++;
                    }
                    
                    if ($pos >= $strlen || $str[$pos] != ':') {
                        throw new \Exception('Invalid string format (missing colon)');
                    }
                    
                    $pos++; // Skip the ':'
                    
                    $len = intval($numLen);
                    if ($pos + $len > $strlen) {
                        throw new \Exception('String length exceeds available data');
                    }
                    
                    $strVal = substr($str, $pos, $len);
                    $pos += $len;
                    return $strVal;
                } else {
                    throw new \Exception('Invalid bencode format: unexpected character at position ' . $pos);
                }
        }
    }
    
    /**
     * Encode a string
     *
     * @param string $str String to encode
     * @return string Bencoded string
     */
    private static function encodeString($str)
    {
        return strlen($str) . ':' . $str;
    }
    
    /**
     * Encode an integer
     *
     * @param int $int Integer to encode
     * @return string Bencoded integer
     */
    private static function encodeInteger($int)
    {
        return 'i' . $int . 'e';
    }
    
    /**
     * Encode a list
     *
     * @param array $list List to encode
     * @return string Bencoded list
     */
    private static function encodeList($list)
    {
        $result = 'l';
        foreach ($list as $value) {
            $result .= self::encode($value);
        }
        $result .= 'e';
        return $result;
    }
    
    /**
     * Encode a dictionary
     *
     * @param array $dict Dictionary to encode
     * @return string Bencoded dictionary
     */
    private static function encodeDict($dict)
    {
        // Sort keys for consistent encoding
        ksort($dict);
        
        $result = 'd';
        foreach ($dict as $key => $value) {
            // Keys must be strings in bencode
            $result .= self::encodeString((string)$key);
            $result .= self::encode($value);
        }
        $result .= 'e';
        return $result;
    }
    
    /**
     * Check if an array is associative
     *
     * @param array $array Array to check
     * @return bool True if associative, false if sequential
     */
    private static function isAssoc($array)
    {
        if (!is_array($array)) {
            return false;
        }
        
        // If array is empty, treat as list
        if (empty($array)) {
            return false;
        }
        
        return array_keys($array) !== range(0, count($array) - 1);
    }
    
    /**
     * Shorthand method to decode torrent file contents
     *
     * @param string $filePath Path to the torrent file
     * @return mixed Decoded torrent data
     * @throws \Exception When file cannot be read or decoded
     */
    public static function decodeTorrentFile($filePath)
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \Exception('Torrent file does not exist or is not readable');
        }
        
        $content = @file_get_contents($filePath);
        if ($content === false) {
            throw new \Exception('Could not read torrent file');
        }
        
        return self::decode($content);
    }
    
    /**
     * Calculate info hash from torrent data
     *
     * @param array $torrentData Decoded torrent data
     * @return string Info hash (uppercase hex string)
     * @throws \Exception When info dict is missing or invalid
     */
    public static function calculateInfoHash($torrentData)
    {
        if (!is_array($torrentData) || !isset($torrentData['info']) || !is_array($torrentData['info'])) {
            throw new \Exception('Invalid torrent data: missing info dictionary');
        }
        
        $encodedInfo = self::encode($torrentData['info']);
        return strtoupper(bin2hex(sha1($encodedInfo, true)));
    }
}