<?php
/**
 * Description of _String
 *
 * @author NapolskyHome
 */
namespace ParseIt;

class _String {

    public static function simpleDiff($str1, $str2, $retAsDiff = true) {
        $a = false; // a - start position, 
        $b = 0; // b - position from the end
        $l = min(mb_strlen($str1), mb_strlen($str2));
        for ($i = 0; $i < $l; $i++) {
            if (mb_substr($str1, $i, 1) !== mb_substr($str2, $i, 1)) {
                break;
            }

            $a = $i + 1;
        }

        for ($i = 1; $i <= $l; $i++) {
            if (mb_substr($str1, -$i, 1) !== mb_substr($str2, -$i, 1)) {
                break;
            }

            $b = $i;
        }

        if ($retAsDiff) {
            if ($a === false) {
                return false;
            }

            if ($b === 0) {
                return $diff = mb_substr($str1, $a);
            } else {
                return $diff = mb_substr($str1, $a, -$b);
            }
        } else {
            return array($a, $b);
        }
    }

    public static function trim($str) {
        $str = preg_replace('/\xc2\xa0/', ' ', $str);
        $str = str_ireplace(' ', ' ', $str);
        $str = trim($str);
        return trim($str, ' ' . ' '); // do not add here chr(0xC2)! it brokes te quotes. Also dont add 0xA0: it brakes the strings ended with russian capital "Р"
    }

    public static function mb_trim($string, $charlist = null) {
        $charlist = preg_quote($charlist);

        if (is_null($charlist)) {
            return trim($string);
        } else {
            return preg_replace('#(^['.$charlist.']+)|(['.$charlist.']+$)#uis', '', $string);
        }
    }

    public static function normalizeSpaces($str) {
        return preg_replace('/[[:blank:]]+/uis', ' ', $str);
    }

    public static function parseNumber($str) {
        $str = _String::trim($str);
        
        // в regexp два разных пробела
        if (!preg_match('#[0-9][0-9\,\.\ \ ]*#is', $str, $m)) {
            return false;
        }

        //$v = str_replace([',', ' '], ['', ''], $m[0]);
        $v = str_replace([',', ' '], ['.', ''], $m[0]);
        
        return floatval($v);
    }
    
    public static function mb_ucfirst($string) {
        $string = mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1);
        return $string;
    }
    
    // clean string and leaves only matter
    public static function core($str)
    {
        $str = _String::normalizePunctuationSpaces($str);
        $str = mb_strtolower(trim($str));
        $str = _String::normalizeSpaces($str);
        return $str;
    }
    
    public static function normalizePunctuationSpaces($str)
    {
        return preg_replace('#[[:blank:]]+([,.;\:\(\)])#uis', '$1', $str); // dont incude "-"
    }

    public static function isTimestamp($string)
    {
        try {
            new DateTime('@' . $string);
        } catch(Exception $e) {
            return false;
        }
        return true;
    }
}