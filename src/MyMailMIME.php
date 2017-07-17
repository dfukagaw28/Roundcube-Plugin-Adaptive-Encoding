<?php
declare(strict_types=1);

namespace Roundcube\Plugins\ComposeJaMessage;

require_once __DIR__ . "/../vendor/pear/mail_mime/Mail/mime.php";

use \Mail_mime;

class MyMailMIME extends Mail_mime
{
    private $message;

    public function __construct(Mail_mime $message)
    {
        $this->message = $message;
    }

    /**
     * Get raw headers (in a bad-mannered way)
     */
    public function getRawHeaders()
    {
        $message_array = (array)$this->message;
        return $message_array["\0*\0headers"];
    }

    public function updateHeaderCharset(string $new_charset, string $current_charset=null)
    {
        if (empty($current_charset)) {
            $current_charset = $this->getHeaderCharset();
        }
        if ($new_charset != $current_charset) {
            $this->updateParam('head_charset', $new_charset);

            $headers = $this->getRawHeaders();
            foreach ($headers as $key => &$val) {
                Util::convert($val, $new_charset, $current_charset, true);
            }
            $encodedHeaders = $this->message->headers($headers, true);
        }
    }

    public function updateTextCharset(string $new_charset, string $current_charset=null)
    {
        if (empty($current_charset)) {
            $current_charset = $this->getTextCharset();
        }
        if ($new_charset != $current_charset) {
            $this->updateParam('text_charset', $new_charset);

            $text = $this->message->getTXTBody();
            self::convert($text, $new_charset, $current_charset, TRUE);
            $this->message->setTXTBody($text);
        }
    }

    /* ======== for flowed content ======== */

    public function applyFF($charset, $flowed, $delsp, $width = 78) {
        if (!empty($flowed)) {
            $text = $this->message->getTXTBody();

            // save the current internal encoding
            $original_internal_encoding = mb_internal_encoding();
            mb_internal_encoding($charset);
            // apply soft line breaks
            $text = FoldText::format_flowed($text, $width, $charset, !empty($delsp));
            // restore the internal encoding
            mb_internal_encoding($original_internal_encoding);

            $this->message->setTXTBody($text);
        }

        // update text_charset
        $this->message->setParam('text_charset', "$charset$flowed$delsp");
    }

    /* ======== misc ======== */
    public function getHeaderCharset()
    {
        return $this->_getCharset('head_charset');
    }

    public function getTextCharset()
    {
        return $this->_getCharset('text_charset');
    }

    private function _getCharset($name)
    {
        $charset = $this->message->getParam($name);
        if (empty($charset)) {
            $charset = 'US-ASCII';
        } else {
            $pos = strpos($charset, ';');
            if ($pos !== false) {
                $charset = substr($charset, 0, $pos);
            }
        }
        return $charset;
    }

    public function updateParam($name, $new_value) {
        $old_value = $this->message->getParam($name);
        if ($old_value != $new_value) {
            //self::DEBUG_LOG( "Setting $name to [$new_value] (old value: [$old_value])" );
            $this->message->setParam($name, $new_value);
        }
    }
}
