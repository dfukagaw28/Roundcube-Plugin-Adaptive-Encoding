<?php
declare(strict_types=1);

namespace dfkgw\AdaptiveEncoding;

use \Symfony\Component\Yaml\Yaml;
use \dfkgw\MailMimeEx\MailMimeEx;
use \dfkgw\MimeFoldFlowedText\FlowingText;

class PriorityEncodingList
{
    public $preferences;
    public $legacyRecipientRules;

    public function __construct(string $configFile='')
    {
        $this->preferences = [];
        if (empty($configFile)) {
            $configFile = __DIR__ . '/config.yml';
        }
        $this->loadConfig($configFile);
    }

    protected function loadConfig(string $filepath)
    {
        $config = (array) Yaml::parse(file_get_contents($filepath));

        $defaultOptions = [
            'head_encoding' => [
                'charset' => null,
                'encoding' => null
            ],
            'text_encoding' => [
                'charset' => null,
                'transfer_encoding' => null,
                'format' => null,
                'delsp' => null
            ]
        ];

        foreach (['head_encoding', 'text_encoding'] as $prefKey) {
            $list = [];
            if (array_key_exists($prefKey, $config)) {
                $options = (array) $config[$prefKey];
                foreach ($options as $option) {
                    $key = $option['key'];
                    $list[$key] = $option + $defaultOptions[$prefKey];
                }
            }
            $this->preferences[$prefKey] = $list;
        }

        if (array_key_exists('legacyRecipientRule', $config)) {
            $this->legacyRecipientRules = (array) $config['legacyRecipientRule'];
        } else {
            $this->legacyRecipientRules = [];
        }
    }

    public function updateHeaderEncoding(MailMimeEx $message)
    {
        $text = join('', $message->getRawHeaders());
        $current_charset = $message->getHeaderCharset();

        $original_encoding = mb_internal_encoding();
        mb_internal_encoding($current_charset);

        $options = $this->preferences['head_encoding'];
        $option = $this->tryCharsets($options, $text);
        if ($option) {
            $message->setParam('head_encoding', $option['encoding']);
            $message->updateHeaderCharset($option['charset']);
            //$message->setParam('head_charset', $option['charset']);
            $message->setHeaders([]);
        }
        mb_internal_encoding($original_encoding);
    }

    public function updateTextEncoding(MailMimeEx $message)
    {
        $text = $message->getTextBody();
        $current_charset = $message->getTextCharset();

        $original_encoding = mb_internal_encoding();
        mb_internal_encoding($current_charset);

        $options = $this->preferences['text_encoding'];
        $option = $this->tryCharsets($options, $text);
        if ($option) {
            $message->setParam('text_encoding', $option['transfer_encoding']);
            $message->setOption('format', $option['format']);
            $message->setOption('delsp', $option['delsp']);
            $message->updateTextCharset($option['charset']);
            //$message->setParam('text_charset', $option['charset']);
            $message->setHeaders([]);

            // Update flowed lines
            $encoding = $option['charset'];
            $text = $message->getTextBody();
            $width = 78;
            $delsp = true;
            $text = FlowingText::fold($text, $width, $encoding, $delsp);
            $message->setTextBody($text);
        }
        mb_internal_encoding($original_encoding);
    }

    /**
     *  Find a character encoding which is applicable to the given text
     */
    protected static function tryCharsets(array &$options, string $text)
    {
        foreach ($options as $key => $option) {
            $new_charset = $option["charset"];
            if (self::tryCharset($text, $new_charset)) {
                return $option;
            } else {
                unset($options[$key]);
            }
        }
        return null;
    }

    protected static function tryCharset(string $text0, string $new_charset)
    {
        try {
            $current_charset = mb_internal_encoding();
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
    public function checkLegacyRecipients(array $headers)
    {
        $legacy = $this->matchRules($headers, $this->legacyRecipientRules);
        if ($legacy) {
            unset($this->preferences['head_encoding']['UTF-8']);
            unset($this->preferences['text_encoding']['UTF-8']);
        }
    }

    /**
     * Check if the mail header matches some of the given rules
     */
    protected static function matchRules(array $headers, array $rules): bool
    {
        foreach ($rules as $rule) {
            if (is_array($rule)) {
                $key = $pattern = $value = '';
                if (array_key_exists('key', $rule)) {
                    $key = (string)$rule['key'];
                }
                if (array_key_exists('pattern', $rule)) {
                    $pattern = (string)$rule['pattern'];
                }
                if (!empty($key) && array_key_exists($key, $headers)) {
                    $value = (string)$headers[$key];
                }
                if (!empty($pattern) && !empty($value) && preg_match($pattern, $value)) {
                    return true;
                }
            }
        }
        return false;
    }
}
