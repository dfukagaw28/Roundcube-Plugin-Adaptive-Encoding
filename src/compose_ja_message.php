<?php
declare(strict_types=1);

require_once "MyMailMIME.php";
require_once "PriorityEncodingList.php";

use Roundcube\Plugins\ComposeJaMessage\PriorityEncodingList;

class compose_ja_message extends rcube_plugin
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
        $message = new MyMailMIME($params['message']);

        // Check legacy recipients who are not compliant with UTF-8
        $this->customSetting->checkLegacyRecipients();

        // Check and update capable encodings
        $this->customSetting->guessHeaderCharset($message);
        $this->customSetting->guessTextCharset($message);

        // Apply setting for flowed content
        $this->applyFF($message);
    }

    private function applyFF(MyMailMIME $message)
    {
        $opt = $this->customSetting->getFirstTextEncoding();
        $charset = $message->getTextCharset();
        $flowed = empty($opt['format']) ? '' : '; format=flowed';
        $delsp = $opt['delsp'];
        $delsp = (empty($flowed) || empty($delsp) || $delsp == 'no') ? '' : '; delsp=yes';
        $width = 78;
        $message->applyFF($charset, $flowed, $delsp, $width);
    }

    private static function DEBUG_LOG ($x)
    {
        rcube::write_log( 'debug', $x );
    }
}
