<?php
declare(strict_types=1);

use dfkgw\AdaptiveEncoding\PriorityEncodingList;
use dfkgw\MailMimeEx\MailMimeEx;

class adaptive_encoding extends rcube_plugin
{
    public $task = 'mail';

    private $customSetting;

    public function init()
    {
        $this->customSetting = new PriorityEncodingList();
        $this->add_hook('message_ready', array ($this, 'message_ready'));
    }

    /*
     * Guess charset for the headers and the text body
     */
    public function message_ready ($params)
    {
        global $OUTPUT;

        try {
            $this->updateEncodings($params);
        } catch (Exception $e) {
            rcube::write_log( 'debug', $e );
            $OUTPUT->show_message('unknown errors', 'error');
            $OUTPUT->send('iframe');
        }

        return $params;
    }

    public function updateEncodings($params)
    {
        $message = new MailMimeEx($params['message']);

        // Check legacy recipients who are not compliant with UTF-8
        $this->customSetting->checkLegacyRecipients();

        // Check and update capable encodings
        $this->customSetting->updateHeaderCharset($message);
        $this->customSetting->updateTextCharset($message);
    }

    private static function DEBUG_LOG ($x)
    {
        rcube::write_log( 'debug', $x );
    }
}
