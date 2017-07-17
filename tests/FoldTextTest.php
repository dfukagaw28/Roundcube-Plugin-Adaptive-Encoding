<?php
declare(strict_types=1);

namespace Roundcube\Plugins\ComposeJaMessage;

require_once __DIR__ . "/../vendor/autoload.php";

use PHPUnit\Framework\TestCase;

final class FoldTextTest extends TestCase
{
    public function testDoNotFoldShortText(): void
    {
        $texts = [
            "ab cde fgh ij",
            "あいうえお かきくけこ"
        ];
        $width = 70;

        foreach ($texts as $text) {
            $expected = $text;
            $actual = FoldText::format_flowed($text, $width);
            $this->assertEquals($expected, $actual);
        }
    }

    public function testCanFold_1(): void
    {
        $text = "ab cde fgh ij";
        $width = 7;
        $expected = "ab cde \r\nfgh ij";

        $actual = FoldText::format_flowed($text, $width);
        $this->assertEquals($expected, $actual);
    }

    public function testCanFold_2(): void
    {
        $text = "a bc def g h ij k";
        $width = 4;
        $expected = "a \r\nbc \r\ndef \r\ng h \r\nij k";

        $actual = FoldText::format_flowed($text, $width);
        $this->assertEquals($expected, $actual);
    }

    public function testCanFold_3(): void
    {
        $text = "あ いう えおか き";
        $width = 11;
        $encoding = 'ISO-2022-JP';
        $expected = "あ \r\nいう \r\nえおか \r\nき";

        $text = mb_convert_encoding($text, $encoding, 'UTF-8');
        $actual = FoldText::format_flowed($text, $width, $encoding);
        $actual = mb_convert_encoding($actual, 'UTF-8', $encoding);
        $this->assertEquals($expected, $actual);
    }

    public function testDoNotFoldLongWord(): void
    {
        $texts = [
            "abcdefghijklmnopqrstuvwxyz",
            "あいうえおかき"
        ];
        $width = 11;
        $encoding = 'ISO-2022-JP';

        foreach ($texts as $text) {
            $expected = $text;

            $text = mb_convert_encoding($text, $encoding, 'UTF-8');
            $actual = FoldText::format_flowed($text, $width, $encoding);
            $actual = mb_convert_encoding($actual, 'UTF-8', $encoding);
            $this->assertEquals($expected, $actual);
        }
    }

    public function testCanFoldDelSpYes_1(): void
    {
        $text = "あいうえおかき";
        $width = 11;
        $encoding = 'ISO-2022-JP';
        $delsp = true;
        $expected = "あい \r\nうえ \r\nおか \r\nき";

        $text = mb_convert_encoding($text, $encoding, 'UTF-8');
        $actual = FoldText::format_flowed($text, $width, $encoding, $delsp);
        $actual = mb_convert_encoding($actual, 'UTF-8', $encoding);
        $this->assertEquals($expected, $actual);
    }

    public function testDoNotFoldLongDelSpYes_1(): void
    {
        $text = "a bc def ghij klmno pqrstuvwxyz";
        $width = 6;
        $encoding = 'ISO-2022-JP';
        $delsp = true;
        $expected = "a bc  \r\ndef  \r\nghij  \r\nklmno  \r\npqrstuvwxyz";

        $text = mb_convert_encoding($text, $encoding, 'UTF-8');
        $actual = FoldText::format_flowed($text, $width, $encoding, $delsp);
        $actual = mb_convert_encoding($actual, 'UTF-8', $encoding);
        $this->assertEquals($expected, $actual);
    }

    public function testDoNotFoldLongDelSpYes_2(): void
    {
        $text = "あいうAAAえおかBBBき";
        $width = 11;
        $encoding = 'ISO-2022-JP';
        $delsp = true;
        $expected = "あい \r\nう \r\nAAA \r\nえお \r\nか \r\nBBBき";

        $text = mb_convert_encoding($text, $encoding, 'UTF-8');
        $actual = FoldText::format_flowed($text, $width, $encoding, $delsp);
        $actual = mb_convert_encoding($actual, 'UTF-8', $encoding);
        $this->assertEquals($expected, $actual);
    }

/*
    public function testKinsoku(): void
    {
        $text = "あいうえぉかき";
        $width = 11;
        $encoding = 'ISO-2022-JP';
        $delsp = true;
        $expected = "あい \r\nう \r\nえぉ \r\nかき";

        $text = mb_convert_encoding($text, $encoding, 'UTF-8');
        $actual = FoldText::format_flowed($text, $width, $encoding, $delsp);
        $actual = mb_convert_encoding($actual, 'UTF-8', $encoding);
        $this->assertEquals($expected, $actual);
    }
*/

}
