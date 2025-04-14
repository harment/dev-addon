<?php
namespace XBTTracker\Util;

/**
 * Class to handle bencoding/decoding of torrent files
 */
class Bencode
{
    /**
     * Decode a bencoded string
     *
     * @param string $str
     * @return mixed
     */
    public static function decode($str)
    {
        $pos = 0;
        return self::bdecode($str, $pos);
    }
    
    /**
     * Encode data to bencoded format
     *
     * @param mixed $data
     * @return string
     */
    public static function encode($data)
    {
        if (is_array($data))
        {
            if (self::isAssoc($data))
            {
                return self::encodeDict($data);
            }
            else
            {
                return self::encodeList($data);
            }
        }
        else if (is_int($data) || is_float($data))
        {
            return self::encodeInteger($data);
        }
        else
        {
            return self::encodeString($data);
        }
    }
    
    /**
     * Helper function for bdecode
     *
     * @param string $str
     * @param int &$pos
     * @return mixed
     */
    private static function bdecode($str, &$pos)
    {
        $strlen = strlen($str);
        if ($pos >= $strlen)
        {
            return null;
        }
        
        $char = $str[$pos];
        
        switch ($char)
        {
            case 'd':
                $pos++;
                $result = [];
                while ($pos < $strlen && $str[$pos] != 'e')
                {
                    $key = self::bdecode($str, $pos);
                    if ($key === null)
                    {
                        return null;
                    }
                    $value = self::bdecode($str, $pos);
                    if ($value === null)
                    {
                        return null;
                    }
                    $result[$key] = $value;
                }
                $pos++;
                return $result;
                
            case 'l':
                $pos++;
                $result = [];
                while ($pos < $strlen && $str[$pos] != 'e')
                {
                    $value = self::bdecode($str, $pos);
                    if ($value === null)
                    {
                        return null;
                    }
                    $result[] = $value;
                }
                $pos++;
                return $result;
                
            case 'i':
                $pos++;
                $numStr = '';
                while ($pos < $strlen && $str[$pos] != 'e')
                {
                    $numStr .= $str[$pos];
                    $pos++;
                }
                $pos++;
                return intval($numStr);
                
            default:
                if (ctype_digit($char))
                {
                    $numLen = '';
                    while ($pos < $strlen && ctype_digit($str[$pos]))
                    {
                        $numLen .= $str[$pos];
                        $pos++;
                    }
                    if ($pos >= $strlen || $str[$pos] != ':')
                    {
                        return null;
                    }
                    $pos++;
                    
                    $len = intval($numLen);
                    $strVal = substr($str, $pos, $len);
                    $pos += $len;
                    return $strVal;
                }
                else
                {
                    return null;
                }
        }
    }
    
    /**
     * Encode a string
     *
     * @param string $str
     * @return string
     */
    private static function encodeString($str)
    {
        return strlen($str) . ':' . $str;
    }
    
    /**
     * Encode an integer
     *
     * @param int $int
     * @return string
     */
    private static function encodeInteger($int)
    {
        return 'i' . $int . 'e';
    }
    
    /**
     * Encode a list
     *
     * @param array $list
     * @return string
     */
    private static function encodeList($list)
    {
        $result = 'l';
        foreach ($list as $value)
        {
            $result .= self::encode($value);
        }
        $result .= 'e';
        return $result;
    }
    
    /**
     * Encode a dictionary
     *
     * @param array $dict
     * @return string
     */
    private static function encodeDict($dict)
    {
        // Sort keys for consistent encoding
        ksort($dict);
        
        $result = 'd';
        foreach ($dict as $key => $value)
        {
            $result .= self::encodeString((string)$key);
            $result .= self::encode($value);
        }
        $result .= 'e';
        return $result;
    }
    
    /**
     * Check if array is associative
     *
     * @param array $array
     * @return bool
     */
    private static function isAssoc($array)
    {
        if (!is_array($array))
        {
            return false;
        }
        
        return array_keys($array) !== range(0, count($array) - 1);
    }
}