<?php

/**
 * Simple Bencode library for PHP
 * For XBT Tracker Integration
 */

class Bencode
{
    /**
     * Decode bencoded data
     *
     * @param string $str Bencoded data
     * @return mixed Decoded data
     */
    public static function decode($str)
    {
        $pos = 0;
        return self::decodeValue($str, $pos);
    }
    
    /**
     * Encode data into bencode format
     *
     * @param mixed $value Data to encode
     * @return string Bencoded data
     */
    public static function encode($value)
    {
        if (is_string($value)) {
            return strlen($value) . ':' . $value;
        } elseif (is_int($value)) {
            return 'i' . $value . 'e';
        } elseif (is_float($value)) {
            return 'i' . intval($value) . 'e';
        } elseif (is_array($value)) {
            // Check if array is associative (dictionary) or sequential (list)
            $isList = array_keys($value) === range(0, count($value) - 1);
            
            $result = $isList ? 'l' : 'd';
            
            if ($isList) {
                foreach ($value as $item) {
                    $result .= self::encode($item);
                }
            } else {
                // Sort dictionary keys
                ksort($value);
                
                foreach ($value as $key => $val) {
                    $result .= self::encode((string)$key) . self::encode($val);
                }
            }
            
            $result .= 'e';
            return $result;
        } elseif (is_bool($value)) {
            return 'i' . ($value ? '1' : '0') . 'e';
        } elseif (is_null($value)) {
            return 'le'; // empty list
        } else {
            throw new Exception('Unable to encode value of type ' . gettype($value));
        }
    }
    
    /**
     * Decode a bencoded value
     *
     * @param string $str Bencoded data
     * @param int &$pos Current position in string
     * @return mixed Decoded value
     */
    private static function decodeValue($str, &$pos)
    {
        if ($pos >= strlen($str)) {
            throw new Exception('Unexpected end of data');
        }
        
        $char = $str[$pos];
        
        switch ($char) {
            case 'i': // Integer
                $pos++;
                $value = self::decodeInteger($str, $pos);
                if ($str[$pos] !== 'e') {
                    throw new Exception('Expected "e" at position ' . $pos);
                }
                $pos++;
                return $value;
                
            case 'l': // List
                $pos++;
                $list = [];
                while ($pos < strlen($str) && $str[$pos] !== 'e') {
                    $list[] = self::decodeValue($str, $pos);
                }
                if ($pos >= strlen($str)) {
                    throw new Exception('Unexpected end of data');
                }
                $pos++;
                return $list;
                
            case 'd': // Dictionary
                $pos++;
                $dict = [];
                while ($pos < strlen($str) && $str[$pos] !== 'e') {
                    $key = self::decodeValue($str, $pos);
                    if (!is_string($key)) {
                        throw new Exception('Dictionary key must be a string at position ' . $pos);
                    }
                    $dict[$key] = self::decodeValue($str, $pos);
                }
                if ($pos >= strlen($str)) {
                    throw new Exception('Unexpected end of data');
                }
                $pos++;
                return $dict;
                
            case '0': case '1': case '2': case '3': case '4':
            case '5': case '6': case '7': case '8': case '9':
                // String
                $value = self::decodeString($str, $pos);
                return $value;
                
            default:
                throw new Exception('Invalid bencoded data at position ' . $pos . ': ' . $char);
        }
    }
    
    /**
     * Decode a bencoded integer
     *
     * @param string $str Bencoded data
     * @param int &$pos Current position in string
     * @return int Decoded integer
     */
    private static function decodeInteger($str, &$pos)
    {
        $start = $pos;
        $end = strpos($str, 'e', $start);
        
        if ($end === false) {
            throw new Exception('Unterminated integer at position ' . $pos);
        }
        
        $value = substr($str, $start, $end - $start);
        $pos = $end;
        
        // Validate integer format
        if ($value === '-0' || (strlen($value) > 1 && $value[0] === '0')) {
            throw new Exception('Invalid integer format at position ' . $start);
        }
        
        return intval($value);
    }
    
    /**
     * Decode a bencoded string
     *
     * @param string $str Bencoded data
     * @param int &$pos Current position in string
     * @return string Decoded string
     */
    private static function decodeString($str, &$pos)
    {
        $start = $pos;
        $colon = strpos($str, ':', $start);
        
        if ($colon === false) {
            throw new Exception('Invalid string format at position ' . $pos);
        }
        
        $lenStr = substr($str, $start, $colon - $start);
        $len = intval($lenStr);
        
        $pos = $colon + 1;
        
        if ($pos + $len > strlen($str)) {
            throw new Exception('String too short at position ' . $pos);
        }
        
        $value = substr($str, $pos, $len);
        $pos += $len;
        
        return $value;
    }
}