<?php

require_once "MyMailMIME.php";

class compose_ja_message extends rcube_plugin
{
    public $task = 'mail';

    // Preferences for charsets in the MIME header
    // FORMAT: KEY => [CHARSET, MIME_ENCODING]
    private $head_encodings = [
        'US-ASCII' => ['US-ASCII',    null],
        'JIS'      => ['ISO-2022-JP', 'base64'],
        'UTF-8'    => ['UTF-8',       'base64'],
        //'default'  => ['ISO-8859-1',  'quoted-printable'],
    ];

    // Preferences for charsets in the text message body
    // FORMAT: KEY => [CHARSET, MIME_ENCODING, FF, DELSP]
    // **** Support for FF and DELSP is limited ****
    private $text_encodings = [
        'US-ASCII' => ['US-ASCII',    null,   'flowed', 'no'],
        'JIS'      => ['ISO-2022-JP', '7bit', 'flowed', 'yes'],
        'UTF-8'    => ['UTF-8',       '8bit', 'flowed', 'yes'],
        //'default'  => ['ISO-8859-1',  'quoted-printable'],
    ];

    private $legacyRecipientRules = [
        ['To', '/@ml\.doshisha\.ac\.jp/'],
        ['Cc', '/@ml\.doshisha\.ac\.jp/'],
        ['Bcc', '/@ml\.doshisha\.ac\.jp/'],
        ['To', '/LEGACYLEGACY/'],
    ];

    public function init()
    {
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

            //rcube::write_log( 'debug', $OUTPUT );

            //$OUTPUT->show_message('Hi there!', 'error');
            //$OUTPUT->send('iframe');

            //self::DEBUG_LOG( $this->head_encodings );
            //self::DEBUG_LOG( $this->text_encodings );

        } catch (Exception $e) {
            rcube::write_log( 'debug', $e );
            //$params['abort'] = true;
            //$params['message'] = 'unknown errors';
            $OUTPUT->show_message('unknown errors', 'error');
            $OUTPUT->send('iframe');
        }

        //$body = $params['message']->getTXTBody();
        //$body = str_replace(["/\r?\n/", " "], ["[CR][LF]<br />", "[SP]"], $body);
        //self::DEBUG_LOG( $body );
        //$OUTPUT->show_message( $body, 'error' );
        //$OUTPUT->send('iframe');

        //rcube::write_log( 'debug', $this );
        return $params;
    }

    public function updateEncodings ($params)
    {
        $message = new MyMailMIME($params['message']);
        //$headers = $message->getRawHeaders();

        rcube::write_log( 'debug', 1111 );
        rcube::write_log( 'debug', $this->head_encodings );
        rcube::write_log( 'debug', $this->text_encodings );

        // check legacy recipients who do not understand UTF-8
        if ($message->matchRules($this->legacyRecipientRules)) {
            unset($this->head_encodings['UTF-8']);
            unset($this->text_encodings['UTF-8']);
        }

        rcube::write_log( 'debug', 2222 );
        rcube::write_log( 'debug', $this->head_encodings );
        rcube::write_log( 'debug', $this->text_encodings );

        // check capable encodings
        $overwrite = TRUE;
        $message->guessHeaderCharset($this->head_encodings, $overwrite);
        $message->guessTextCharset  ($this->text_encodings, $overwrite);

        // apply setting for flowed content
        $message->applyFF($this->text_encodings);

        rcube::write_log( 'debug', 3333 );
        $message_array = (array)($params['message']);
        $headers = $message_array["\0*\0headers"];
        rcube::write_log( 'debug', $headers["To"] );
    }

    private static function DEBUG_LOG ($x)
    {
        rcube::write_log( 'debug', $x );
    }
}
