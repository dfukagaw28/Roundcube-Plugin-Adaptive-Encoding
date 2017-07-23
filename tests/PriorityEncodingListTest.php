<?php
declare(strict_types=1);

namespace dfkgw\AdaptiveEncoding;

use \PHPUnit\Framework\TestCase;
use \dfkgw\MailMimeEx\MailMimeEx;

final class PriorityEncodingListTest extends TestCase
{
    public function testConstructPriorityEncodingList(): void
    {
        $config = new PriorityEncodingList();
        $this->assertInstanceOf(PriorityEncodingList::class, $config);
    }

    public function testLoadConfig_empty(): void
    {
        $config = new PriorityEncodingList(__DIR__ . '/config_empty.yml');
        $this->assertInstanceOf(PriorityEncodingList::class, $config);
    }

    public function testGuessEncoding_config1_short(): void
    {
        $message = new MailMimeEx(['Subject' => 'This is test'], 'Hello!');

        $config = new PriorityEncodingList(__DIR__ . '/config1.yml');
        $config->updateHeaderEncoding($message);
        $config->updateTextEncoding($message);

        $headers = $message->getRawHeaders();
        $this->assertEquals('This is test', $headers['Subject']);
        $this->assertEquals('text/plain; charset=US-ASCII; format=flowed', $headers['Content-Type']);
        $this->assertEquals('7bit', $headers['Content-Transfer-Encoding']);
        $text = $message->getTextBody();
        $this->assertEquals('Hello!', $text);
    }

    public function testGuessEncoding_config1_long(): void
    {
        $longText = str_repeat('hi ', 80);
        $foldedText = join(
            "\r\n",
            [
                'hi' . str_repeat(' hi', 22),
                str_repeat(' hi', 26),
                str_repeat(' hi', 26),
                str_repeat(' hi', 5) . ' ',
            ]);
        $message = new MailMimeEx(['Subject' => $longText], $longText);

        $config = new PriorityEncodingList(__DIR__ . '/config1.yml');
        $config->updateHeaderEncoding($message);
        $config->updateTextEncoding($message);

        $headers = $message->getRawHeaders();
        $this->assertEquals($longText, $headers['Subject']);
        $this->assertEquals('text/plain; charset=US-ASCII; format=flowed', $headers['Content-Type']);
        $this->assertEquals('7bit', $headers['Content-Transfer-Encoding']);
        $headers = $message->getHeaders();
        $this->assertEquals($foldedText, $headers['Subject']);
        $this->assertEquals('text/plain; charset=US-ASCII; format=flowed', $headers['Content-Type']);
        $this->assertEquals('7bit', $headers['Content-Transfer-Encoding']);

        $text = $message->getTextBody();
        $this->assertEquals($longText, $text);
    }

    public function testGuessEncoding_config1_unicode_1(): void
    {
        $message = new MailMimeEx(['Subject' => 'Rád tě poznávám'], 'Hello!');

        $config = new PriorityEncodingList(__DIR__ . '/config1.yml');
        $config->updateHeaderEncoding($message);
        $config->updateTextEncoding($message);

        $headers = $message->getRawHeaders();
        $this->assertEquals('Rád tě poznávám', $headers['Subject']);
        $this->assertEquals('text/plain; charset=US-ASCII; format=flowed', $headers['Content-Type']);
        $this->assertEquals('7bit', $headers['Content-Transfer-Encoding']);
        $headers = $message->getHeaders();
        $this->assertEquals('=?UTF-8?Q?R=C3=A1d_t=C4=9B_pozn=C3=A1v=C3=A1m?=', $headers['Subject']);
        $this->assertEquals('text/plain; charset=US-ASCII; format=flowed', $headers['Content-Type']);
        $this->assertEquals('7bit', $headers['Content-Transfer-Encoding']);

        $text = $message->getTextBody();
        $this->assertEquals('Hello!', $text);
    }

    public function testGuessEncoding_config1_unicode_2(): void
    {
        $message = new MailMimeEx(['Subject' => 'Rád tě poznávám'], 'Hyvää päivää!');

        $config = new PriorityEncodingList(__DIR__ . '/config1.yml');
        $config->updateHeaderEncoding($message);
        $config->updateTextEncoding($message);

        $headers = $message->getRawHeaders();
        $this->assertEquals('Rád tě poznávám', $headers['Subject']);
        $this->assertEquals('text/plain; charset=UTF-8; format=flowed', $headers['Content-Type']);
        $this->assertEquals('8bit', $headers['Content-Transfer-Encoding']);
        $headers = $message->getHeaders();
        $this->assertEquals('=?UTF-8?Q?R=C3=A1d_t=C4=9B_pozn=C3=A1v=C3=A1m?=', $headers['Subject']);
        $this->assertEquals('text/plain; charset=UTF-8; format=flowed', $headers['Content-Type']);
        $this->assertEquals('8bit', $headers['Content-Transfer-Encoding']);

        $text = $message->getTextBody();
        $this->assertEquals('Hyvää päivää!', $text);
    }


    public function testGuessEncoding_config2_short(): void
    {
        $message = new MailMimeEx(['Subject' => 'This is test'], 'Hello!');

        $config = new PriorityEncodingList(__DIR__ . '/config2.yml');
        $config->updateHeaderEncoding($message);
        $config->updateTextEncoding($message);

        $headers = $message->getRawHeaders();
        $this->assertEquals('This is test', $headers['Subject']);
        $this->assertEquals('text/plain; charset=US-ASCII; format=flowed', $headers['Content-Type']);
        $this->assertEquals('7bit', $headers['Content-Transfer-Encoding']);
        $text = $message->getTextBody();
        $this->assertEquals('Hello!', $text);
    }

    public function testGuessEncoding_config2_long(): void
    {
        $longText = str_repeat('hi ', 80);
        $foldedText = join(
            "\r\n",
            [
                'hi' . str_repeat(' hi', 22),
                str_repeat(' hi', 26),
                str_repeat(' hi', 26),
                str_repeat(' hi', 5) . ' ',
            ]);
        $message = new MailMimeEx(['Subject' => $longText], $longText);

        $config = new PriorityEncodingList(__DIR__ . '/config2.yml');
        $config->updateHeaderEncoding($message);
        $config->updateTextEncoding($message);

        $headers = $message->getRawHeaders();
        $this->assertEquals($longText, $headers['Subject']);
        $this->assertEquals('text/plain; charset=US-ASCII; format=flowed', $headers['Content-Type']);
        $this->assertEquals('7bit', $headers['Content-Transfer-Encoding']);
        $headers = $message->getHeaders();
        $this->assertEquals($foldedText, $headers['Subject']);
        $this->assertEquals('text/plain; charset=US-ASCII; format=flowed', $headers['Content-Type']);
        $this->assertEquals('7bit', $headers['Content-Transfer-Encoding']);

        $text = $message->getTextBody();
        $this->assertEquals($longText, $text);
    }

    public function testGuessEncoding_config2_jis_1(): void
    {
        $longText = str_repeat('あ', 30);
        $jisText = mb_convert_encoding($longText, 'ISO-2022-JP', 'UTF-8');
        $mimeEncodedText = "=?ISO-2022-JP?B?GyRCJCIkIiQiJCIkIiQiJCIkIiQiJCIkIiQiJCIkIhsoQg==?=\r\n"
            . " =?ISO-2022-JP?B?GyRCJCIkIiQiJCIkIiQiJCIkIiQiJCIkIiQiJCIkIiQiJCIbKEI=?=";
        $message = new MailMimeEx(['Subject' => $longText], $longText);

        $config = new PriorityEncodingList(__DIR__ . '/config2.yml');
        $config->updateHeaderEncoding($message);
        $config->updateTextEncoding($message);

        $headers = $message->getRawHeaders();
        $this->assertEquals($jisText, $headers['Subject']);
        $this->assertEquals('text/plain; charset=ISO-2022-JP; format=flowed; delsp=yes', $headers['Content-Type']);
        $this->assertEquals('7bit', $headers['Content-Transfer-Encoding']);
        $headers = $message->getHeaders();
        $this->assertEquals($mimeEncodedText, $headers['Subject']);
        $this->assertEquals('text/plain; charset=ISO-2022-JP; format=flowed; delsp=yes', $headers['Content-Type']);
        $this->assertEquals('7bit', $headers['Content-Transfer-Encoding']);

        $text = $message->getTextBody();
        $this->assertEquals($jisText, $text);
    }

    public function testGuessEncoding_config2_unicode_1(): void
    {
        $longText = str_repeat('あ', 30) . '①';
        $mimeEncodedText = "=?UTF-8?Q?=E3=81=82=E3=81=82=E3=81=82=E3=81=82=E3=81=82=E3=81=82?=\r\n"
            . " =?UTF-8?Q?=E3=81=82=E3=81=82=E3=81=82=E3=81=82=E3=81=82=E3=81=82=E3=81=82?=\r\n"
            . " =?UTF-8?Q?=E3=81=82=E3=81=82=E3=81=82=E3=81=82=E3=81=82=E3=81=82=E3=81=82?=\r\n"
            . " =?UTF-8?Q?=E3=81=82=E3=81=82=E3=81=82=E3=81=82=E3=81=82=E3=81=82=E3=81=82?=\r\n"
            . " =?UTF-8?Q?=E3=81=82=E3=81=82=E3=81=82=E2=91=A0?=";
        $message = new MailMimeEx(['Subject' => $longText], $longText);

        $config = new PriorityEncodingList(__DIR__ . '/config2.yml');
        $config->updateHeaderEncoding($message);
        $config->updateTextEncoding($message);

        $headers = $message->getRawHeaders();
        $this->assertEquals($longText, $headers['Subject']);
        $this->assertEquals('text/plain; charset=UTF-8; format=flowed; delsp=yes', $headers['Content-Type']);
        $this->assertEquals('8bit', $headers['Content-Transfer-Encoding']);
        $headers = $message->getHeaders();
        $this->assertEquals($mimeEncodedText, $headers['Subject']);
        $this->assertEquals('text/plain; charset=UTF-8; format=flowed; delsp=yes', $headers['Content-Type']);
        $this->assertEquals('8bit', $headers['Content-Transfer-Encoding']);

        $text = $message->getTextBody();
        $this->assertEquals($longText, $text);
    }
}
