<?php
declare(strict_types=1);

namespace Roundcube\Plugins\ComposeJaMessage;

class Util
{
    /**
     *  Get the first value of an array
     *
     *  NOTE: reset() sets the internal pointer of an array to its first element.
     *      c.f. http://php.net/manual/en/function.reset.php
     */
    public static function getFirstKey($array)
    {
        reset($array);
        return key($array);
    }

    /**
     *  Get the first value of an array
     *
     *  NOTE: reset() returns the value of the first array element, or FALSE if the array is empty.
     *      c.f. http://php.net/manual/en/function.reset.php
     */
    public static function getFirstValue($array)
    {
        return reset($array);
    }

    public static function convert(&$value, $to_encoding, $from_encoding, $overwrite=FALSE)
    {
        $matches = [];
        if (!preg_match('/[-_0-9A-Za-z]+/', $from_encoding, $matches)) {
            return $value;
        }
        $from_encoding = $matches[0];
        if (is_array($value)) {
            $value_new = [];
            foreach ($value as &$val) {
                $value_new[] = Util::convert($val, $to_encoding, $from_encoding, $overwrite);
            }
        } else {
            $value_new = mb_convert_encoding($value, $to_encoding, $from_encoding);
            if ($overwrite && ($value != $value_new)) {
                //Util::DEBUG_LOG( "OLD:".bin2hex($value) );
                //Util::DEBUG_LOG( "NEW:".bin2hex($value_new) );
                $value = $value_new;
            }
        }
        return $overwrite ? $value : $value_new;
    }

    public static function DEBUG_LOG($x)
    {
        rcube::write_log( 'debug', $x );
    }
}
