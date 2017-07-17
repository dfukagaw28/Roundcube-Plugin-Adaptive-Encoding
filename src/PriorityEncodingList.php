<?php
declare(strict_types=1);

namespace Roundcube\Plugins\ComposeJaMessage;

use Yosymfony\Toml\Toml;

class PriorityEncodingList
{
    private $head_encodings;
    private $text_encodings;
    private $legacyRecipientRules;

    public function __construct()
    {
        $this->loadConfig(__DIR__ . '/config.toml');
    }

    private function loadConfig(string $filepath)
    {
        $config = Toml::Parse($filepath);
        
        foreach ($config["head_encodings"] as $item) {
            $key = $item["key"];
            $val = $item;
            $this->head_encodings[$key] = $val;
        }

        foreach ($config["text_encodings"] as $item) {
            $key = $item["key"];
            $val = $item;
            $this->text_encodings[$key] = $val;
        }

        $this->legacyRecipientRules[$key] = $config["legacyRecipientRules"];
    }

    public function guessHeaderCharset(MyMailMIME $message)
    {
        $text = join('', $message->getRawHeaders());
        $current_charset = $message->getHeaderCharset();

        $this->tryHeaderCharsets($text, $current_charset);

        $option = $this->getFirstHeadEncoding();
        $new_charset = $option["charset"];

        $message->updateHeaderCharset($new_charset, $current_charset);

        $message->updateParam('head_encoding', $option["encoding"]);
    }

    public function guessTextCharset(MyMailMIME $message)
    {
        $text = $message->message->getTXTBody();
        $current_charset = $message->getTextCharset();

        $this->tryTextCharsets($text, $current_charset);

        $option = $this->getFirstTextEncoding();
        $new_charset = $option["charset"];

        $message->updateTextCharset($new_charset, $current_charset);

        $message->updateParam('text_encoding', $option["transfer_encoding"]);
    }

    private function getFirstHeadEncoding()
    {
        return Util::getFirstValue($this->head_encodings);
    }

    private function getFirstTextEncoding()
    {
        return Util::getFirstValue($this->text_encodings);
    }

    private function tryHeaderCharsets(string $text, string $current_charset)
    {
        self::tryCharsets($this->head_encodings, $text, $current_charset);
    }

    private function tryTextCharsets(string $text, string $current_charset)
    {
        self::tryCharsets($this->text_encodings, $text, $current_charset);
    }

    private static function tryCharsets(array &$options, string $text, string $current_charset)
    {
        foreach ($options as $key => $option) {
            $new_charset = $option["charset"];
            if (self::tryCharset($text, $new_charset, $current_charset)) {
                break;
            } else {
                unset($options[$key]);
            }
        }
    }

    private static function tryCharset(string $text0, string $new_charset, string $current_charset)
    {
        try {
            $text1 = mb_convert_encoding($text0, $new_charset, $current_charset);
            $text2 = mb_convert_encoding($text1, $current_charset, $new_charset);
            if (strcmp($text0, $text2) == 0) {
                return true;
            }
        } catch (Exception $e) {
            // ignore
        }
        return false;
    }

    /* ======== Legacy recipients ======== */

    /*
     * Check if recipients are compliant with UTF8 or not
     * (using BLACKLIST)
     */
    public function checkLegacyRecipients(MyMailMIME $message)
    {
        $headers = $message->getRawHeaders();
        $legacy = $this->matchRules($headers, $this->legacyRecipientRules);
        if ($legacy) {
            unset($this->head_encodings['UTF-8']);
            unset($this->text_encodings['UTF-8']);
        }
    }

    /**
     * Check if the mail header matches some of the given rules
     */
    private static function matchRules(string $headers, array $rules): bool
    {
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
}
