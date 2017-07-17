<?php
declare(strict_types=1);

namespace Roundcube\Plugins;

class FoldText
{
    /**
     * Wrap the given text to comply with RFC 3676
     * An extended version of rcube_mime::format_flowed()
     * https://github.com/roundcube/roundcubemail/blob/master/program/lib/Roundcube/rcube_mime.php
     *
     * @param string $text     Text to wrap (encoded by mb_internal_encoding())
     * @param int    $length   Length
     * @param string $encoding Character encoding of $text
     * @param bool   $delsp    DelSp (c.f. RFC 3676)
     */
    public static function format_flowed(
        string $text,
        int $length = 72,
        string $encoding = null,
        bool $delsp = false
    ) {
        $lines = preg_split('/\r?\n/', $text);

        foreach ($lines as $idx => $line) {
            // Do not touch signature separators
            if ($line == '-- ') {
                continue;
            }
            // Do not touch short lines
            if (strlen($line) < $length) {
                continue;
            }
            // Quotes
            if ($level = strspn($line, '>')) {
                // remove quote chars
                $line = substr($line, $level);
                // remove (optional) space-staffing and spaces before the line end
                $line = rtrim($line, ' ');
                if ($line[0] === ' ') {
                    $line = substr($line, 1);
                }
                $prefix = str_repeat('>', $level) . ' ';
                $line = self::wordwrap($line, $length - $level - 1, " \r\n$prefix", $encoding, $delsp);
                $line = $prefix . $line;
            // Others
            } else {
                $line = self::wordwrap($line, $length, " \r\n", $encoding, $delsp);
                // space-stuffing
                $line = preg_replace('/(^|\r\n)(From| |>)/', '\\1 \\2', $line);
            }
            $lines[$idx] = $line;
        }

        return implode("\r\n", $lines);
    }

    /**
     * Wrap a 'long' line of text into 'short' chunks
     * and join the chunks with a given separator
     */
    public static function wordwrap(
        string $line,
        int $width = 72,
        string $sep = " \r\n",
        string $encoding = null,
        bool $delsp = false
    ): string {
        if (strlen($line) <= $width) {
            return $line;
        }
        if (!$encoding) {
            $encoding = mb_internal_encoding();
        }
        $text = mb_convert_encoding($line, 'UTF-8', $encoding);
        $text = new FlowingText($text, $encoding, $delsp);
        $chunks = $text->findAllChunks($width);
        return join($sep, $chunks);
    }
}

class FlowingText
{
    private $text; // UTF-8 string
    private $encoding;
    private $delsp;

    private $chunks;

    private $bytesRead;
    private $encodedChunk;
    private $encodedWidth;

    public function __construct(
        string $text = '',
        string $encoding = '',
        bool $delsp = false
    ) {
        $this->text = $text;
        $this->encoding = $encoding;
        $this->delsp = $delsp;

        $this->chunks = [];
        $this->resetChunk();
    }

    public function resetChunk()
    {
        $this->bytesRead = 0;
        $this->encodedChunk = '';
        $this->encodedWidth = 0;
    }

    public function findAllChunks(int $width)
    {
        $updated = true;
        while ($updated) {
            $updated = $this->findChunk($width);
        }
        if (!empty($this->text)) {
            $chunk_encoded = mb_convert_encoding($this->text, $this->encoding, 'UTF-8');
            $this->chunks[] = $chunk_encoded;
        }
        return $this->chunks;
    }

    public function findChunk(int $width)
    {
        if (!$this->delsp) {
            // Accept the first word
            $nextSP = strpos($this->text, ' ', $this->bytesRead);
            if ($nextSP === false) {
                $this->testChunk(strlen($this->text));
            } else {
                $this->testChunk($nextSP + 1);
            }
        }

        // Accept next words as many as possible
        while ($this->encodedWidth < $width) {
            $nextSP = strpos($this->text, ' ', $this->bytesRead);
            $found = $nextSP !== false;
            if ($found) {
                $updated = $this->testChunk($nextSP + 1, $width);
                if ($updated) {
                    continue;
                }
            } else {
                $this->testChunk(strlen($this->text), $width);
            }
            break;
        }

        if ($this->delsp) {
            // Accept next graphemes as many as possible
            while (true) {
                // Find the next grapheme
                $token = $this->getNextToken($nextPos);
                if (empty($token) || $nextPos <= $this->bytesRead) {
                    // ERROR: Failed to find a grapheme
                    break;
                }
                $updated = $this->testChunk($nextPos, $width - 1);
                if (!$updated) {
                    break;
                }
            }
        }

        $updated = $this->confirmChunk();
        return $updated;
    }

    public function testChunk(int $bytes, int $width = -1)
    {
        $chunk_utf8 = substr($this->text, 0, $bytes);
        $chunk_encoded = mb_convert_encoding($chunk_utf8, $this->encoding, 'UTF-8');
        $chunk_width = strlen($chunk_encoded);
        if ($width < 0 || $chunk_width <= $width) {
            $this->bytesRead = $bytes;
            $this->encodedChunk = $chunk_encoded;
            $this->encodedWidth = $chunk_width;
            return true;
        } else {
            return false;
        }
    }

    public function confirmChunk()
    {
        if ($this->bytesRead <= 0) {
            return false;
        }
        $encodedChunk = $this->encodedChunk;
        if (!$this->delsp) {
            $encodedChunk = rtrim($encodedChunk, ' ');
        }
        $this->text = substr($this->text, $this->bytesRead);
        $this->chunks[] = $encodedChunk;
        $this->resetChunk();
        return true;
    }

    public function getNextToken(&$nextPos)
    {
        $offset = $this->bytesRead;
        $text = substr($this->text, $offset);
        if (preg_match("/^[\x21-\x7e]+/", $text, $matches) === 1) {
            $token = $matches[0];
            $nextPos = $offset + strlen($token);
        } else {
            $token = grapheme_extract($this->text, 1, GRAPHEME_EXTR_COUNT, $offset, $next);
            /*
            if ($next > $offset) {
                $forbiddenAtStart = ",)]｝、〕〉》」』】〙〗〟’”｠»"
                    . "ゝゞーァィゥェォッャュョヮヵヶぁぃぅぇぉっゃゅょゎゕゖㇰㇱㇲㇳㇴㇵㇶㇷㇸㇹㇷ゚ㇺㇻㇼㇽㇾㇿ々〻"
                    . "‐゠–〜～" . "?!‼⁇⁈⁉" . "・:;" . "。.";
                $pattern = "/^[" . preg_quote($forbiddenAtStart) . "\\/]+/";
                $text = substr($this->text, $next);
                if (preg_match($pattern, $text, $matches) === 1) {
                    var_dump( [$text, $matches[0]] );
                    $token .= $matches[0];
                    $next += strlen($matches[0]);
                }
            }
            */
            $nextPos = $next;
        }
        return $token;
    }
}
