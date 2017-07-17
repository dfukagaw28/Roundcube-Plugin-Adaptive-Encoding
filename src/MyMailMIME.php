<?php

require_once __DIR__ . "/FoldText.php";

use Roundcube\Plugins\FoldText;

class MyMailMIME extends Mail_mime
{
    private $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    /**
     * Get raw headers
     */
    public function getRawHeaders()
    {
        $message_array = (array)$this->message;
        return $message_array["\0*\0headers"];
    }

    public function updateHeaderCharset ($new_charset, $current_charset=null)
    {
        if (empty($current_charset)) {
            $current_charset = $this->getHeaderCharset();
        }
        if ($new_charset == $current_charset) {
            return;
        }
        $this->updateParam('head_charset', $new_charset);

        $headers = $this->getRawHeaders();
        foreach ($headers as $key => &$val) {
            self::convert($val, $new_charset, $current_charset, TRUE);
        }
        $encodedHeaders = $this->message->headers($headers, TRUE);

        self::DEBUG_LOG( 99999 );
        self::DEBUG_LOG( $headers["To"] );
        $temp = $this->message; $temp = (array)$temp; $temp = $temp["\0*\0headers"];
        self::DEBUG_LOG( "To(raw)=" . $temp["To"] );
        self::DEBUG_LOG( "To(enc)=" . $encodedHeaders["To"] );
    }

    public function updateTextCharset ($new_charset, $current_charset=null)
    {
        if (empty($current_charset)) {
            $current_charset = $this->getTextCharset();
        }
        if ($new_charset == $current_charset) {
            return;
        }
        $this->updateParam('text_charset', $new_charset);

        $text = $this->message->getTXTBody();
        self::DEBUG_LOG( "text=[$text]" );
        self::convert($text, $new_charset, $current_charset, TRUE);
        self::DEBUG_LOG( "text=[$text]" );
        $this->message->setTXTBody($text);
    }

    /**
     * Guess an appropriate character encoding for the MIME header, and
     */
    public function guessHeaderCharset (&$encodings, $overwrite=false)
    {
        $text = implode('', $this->getRawHeaders());
        $current_charset = $this->getHeaderCharset();

        foreach ($encodings as $key => $encoding) {
            $new_charset = $encoding[0];
            if (self::tryCharset($text, $new_charset, $current_charset)) {
                break;
            } else {
                unset($encodings[$key]);
            }
        }

        //self::DEBUG_LOG( $encodings );

        if ($overwrite) {
            $new_charset = self::getFirstValue($encodings)[0];
            $this->updateHeaderCharset($new_charset, $current_charset);

            $new_encoding = self::getFirstValue($encodings)[1];
            $this->updateParam('head_encoding', $new_encoding);
        }
    }

    /**
     * Guess an appropriate character encoding for the text body, and
     * convert the text.
     *
     * @return void
     */
    public function guessTextCharset (&$encodings, $overwrite=false)
    {
        $text = $this->message->getTXTBody();
        $current_charset = $this->getTextCharset();

        foreach ($encodings as $key => $encoding) {
            $new_charset = $encoding[0];
            if (self::tryCharset($text, $new_charset, $current_charset)) {
                break;
            } else {
                unset($encodings[$key]);
            }
        }

        //self::DEBUG_LOG( $encodings );

        if ($overwrite) {
            $new_charset = self::getFirstValue($encodings)[0];
            $this->updateTextCharset($new_charset, $current_charset);
            $new_encoding = self::getFirstValue($encodings)[1];
            $this->updateParam('text_encoding', $new_encoding);
        }
    }

    /**
     */
    public static function tryCharset($text0, $new_charset, $current_charset)
    {
        try {
            $text1 = self::convert($text0, $new_charset, $current_charset);
            $text2 = self::convert($text1, $current_charset, $new_charset);
            if (strcmp($text0, $text2) == 0) {
                self::DEBUG_LOG( "Encoding [$text0] by $new_charset... SUCCESS!" );
                return true;
            } else {
                self::DEBUG_LOG( ['differs', $text0, $text2] );
            }
        } catch (Exception $e) {
            // ignore
        }
        self::DEBUG_LOG( "Encoding [$text0] by $new_charset... FAIL!" );
        return false;
    }

    /**
     * Get the first key of an array
     */
    private static function getFirstKey ($array)
    {
        reset($array);
        return key($array);
    }

    /**
     * Get the first value of an array
     */
    private static function getFirstValue ($array)
    {
        return reset($array);
    }

    private static function convert (&$value, $to_encoding, $from_encoding, $overwrite=FALSE)
    {
        $matches = [];
        if (!preg_match('/[-_0-9A-Za-z]+/', $from_encoding, $matches)) {
            return $value;
        }
        $from_encoding = $matches[0];
        if (is_array($value)) {
            $value_new = [];
            foreach ($value as &$val) {
                $value_new[] = self::convert($val, $to_encoding, $from_encoding, $overwrite);
            }
        } else {
            $value_new = mb_convert_encoding($value, $to_encoding, $from_encoding);
            if ($overwrite && ($value != $value_new)) {
                self::DEBUG_LOG( "OLD:".bin2hex($value) );
                self::DEBUG_LOG( "NEW:".bin2hex($value_new) );
                $value = $value_new;
            }
        }
        return $overwrite ? $value : $value_new;
    }

    /* ======== for flowed content ======== */

    public function applyFF ($encodings) {
        $setting = self::getFirstValue($encodings);
        $charset = $this->getTextCharset();
        $flowed = empty($setting[2]) ? '' : '; format=flowed';
        $delsp = (empty($flowed) || empty($setting[3]) || $setting[3] == 'no') ? '' : '; delsp=yes';
        $line_length = 78;

        if (!empty($flowed)) {
            $text = $this->message->getTXTBody();

            // save the current internal encoding
            $original_internal_encoding = mb_internal_encoding();
            mb_internal_encoding($charset);
            // apply soft line breaks
            self::DEBUG_LOG( 'START: format_flowed()' );
            $text = FoldText::format_flowed($text, $line_length, $charset, !empty($delsp));
            self::DEBUG_LOG( 'END: format_flowed()' );
            // restore the internal encoding
            mb_internal_encoding($original_internal_encoding);

            $this->message->setTXTBody($text);
        }

        // update text_charset
        $this->message->setParam('text_charset', "$charset$flowed$delsp");
    }

    /* ======== Legacy recipients ======== */

    /**
     * Check if the mail header matches some of the given rules
     *
     * @param  array $rules
     *
     * @return bool  TRUE if one or more rules match the message, FALSE otherwise
     */
    public function matchRules ($rules)
    {
        $headers = $this->getRawHeaders();

        foreach ($rules as $rule) {
            try {
                $key = $rule[0];
                $pattern = $rule[1];
                $value = $headers[$key];
                if (preg_match($pattern, $value)) {
                    return true;
                }
            } catch (Exception $e) {
                // ignore errors
            }
        }

        return false;
    }

    /* ======== misc ======== */
    public function getHeaderCharset ()
    {
        return $this->_getCharset('head_charset');
    }

    public function getTextCharset ()
    {
        return $this->_getCharset('text_charset');
    }

    private function _getCharset ($name)
    {
        $charset = $this->message->getParam($name);
        if (empty($charset)) { return 'US-ASCII'; }
        if ($pos = strpos($charset, ';')) {
            $charset = substr($charset, 0, $pos);
        }
        return $charset;
    }

    private function updateParam ($name, $new_value) {
        $old_value = $this->message->getParam($name);
        if ($old_value != $new_value) {
            self::DEBUG_LOG( "Setting $name to [$new_value] (old value: [$old_value])" );
            $this->message->setParam($name, $new_value);
        }
    }

    private static function DEBUG_LOG ($x)
    {
        rcube::write_log( 'debug', $x );
    }
}
