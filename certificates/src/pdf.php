<?php
declare(strict_types=1);

require_once __DIR__ . '/ttf.php';

/**
 * Одностраничный PDF: фон JPEG и строки текста шрифтами TrueType.
 */
final class CertPdf
{
    private float $width;
    private float $height;
    private ?array $image = null;
    /** @var array<string, TtfFont> */
    private array $fonts = [];
    /** @var array<string, array<int, int>> gid => codepoint */
    private array $used = [];
    private string $content = '';

    public function __construct(float $width, float $height)
    {
        $this->width = $width;
        $this->height = $height;
    }

    public function setBackground(string $jpeg, int $pxWidth, int $pxHeight, int $channels = 3): void
    {
        $this->image = ['data' => $jpeg, 'w' => $pxWidth, 'h' => $pxHeight, 'channels' => $channels];
    }

    public function addFont(string $key, TtfFont $font): void
    {
        $this->fonts[$key] = $font;
        $this->used[$key] = $this->used[$key] ?? [];
    }

    /**
     * Строка текста; x, y — начало базовой линии в пунктах от левого нижнего угла.
     *
     * @param array{0:float,1:float,2:float} $rgb
     */
    public function text(string $fontKey, float $size, array $rgb, float $x, float $y, string $text): void
    {
        $font = $this->fonts[$fontKey] ?? null;
        if ($font === null || $text === '') {
            return;
        }
        $hex = '';
        foreach (mb_str_split($text, 1, 'UTF-8') as $ch) {
            $cp = mb_ord($ch, 'UTF-8');
            $gid = $font->glyphId($cp);
            $this->used[$fontKey][$gid] = $cp;
            $hex .= sprintf('%04X', $gid);
        }
        $index = array_search($fontKey, array_keys($this->fonts), true) + 1;
        $this->content .= sprintf(
            "BT /F%d %s Tf %s %s %s rg %s %s Td <%s> Tj ET\n",
            $index,
            self::num($size),
            self::num($rgb[0]),
            self::num($rgb[1]),
            self::num($rgb[2]),
            self::num($x),
            self::num($y),
            $hex
        );
    }

    public function output(string $title = ''): string
    {
        $objects = [];
        $add = static function (string $body) use (&$objects): int {
            $objects[] = $body;

            return count($objects);
        };
        $stream = static function (string $dict, string $data): string {
            return '<< ' . $dict . ' /Length ' . strlen($data) . " >>\nstream\n" . $data . "\nendstream";
        };

        $catalog = $add('');
        $pages = $add('');
        $page = $add('');

        $resources = '';
        $draw = '';
        if ($this->image !== null) {
            $cs = $this->image['channels'] === 1 ? '/DeviceGray' : '/DeviceRGB';
            $img = $add($stream(
                '/Type /XObject /Subtype /Image /Width ' . $this->image['w'] . ' /Height ' . $this->image['h']
                . ' /ColorSpace ' . $cs . ' /BitsPerComponent 8 /Filter /DCTDecode',
                $this->image['data']
            ));
            $resources .= '/XObject << /Im1 ' . $img . ' 0 R >> ';
            $draw = 'q ' . self::num($this->width) . ' 0 0 ' . self::num($this->height) . " 0 0 cm /Im1 Do Q\n";
        }

        $fontRefs = '';
        $i = 0;
        foreach ($this->fonts as $key => $font) {
            $i++;
            $used = $this->used[$key] ?? [];
            if ($used === []) {
                continue;
            }
            ksort($used);
            $scale = 1000 / $font->unitsPerEm;
            $tag = self::subsetTag($key . implode(',', array_keys($used)));
            $base = $tag . '+' . $font->name;

            $raw = $font->subset(array_fill_keys(array_keys($used), true));
            $fontFile = $add($stream('/Filter /FlateDecode /Length1 ' . strlen($raw), gzcompress($raw, 6)));

            $flags = 32 | (abs($font->italicAngle) > 0.01 ? 64 : 0);
            $descriptor = $add(
                '<< /Type /FontDescriptor /FontName /' . $base . ' /Flags ' . $flags
                . ' /FontBBox [' . implode(' ', array_map(static fn (int $v) => (string) (int) round($v * $scale), $font->bbox)) . ']'
                . ' /ItalicAngle ' . self::num($font->italicAngle)
                . ' /Ascent ' . (int) round($font->ascent * $scale)
                . ' /Descent ' . (int) round($font->descent * $scale)
                . ' /CapHeight ' . (int) round($font->capHeight * $scale)
                . ' /StemV 80 /FontFile2 ' . $fontFile . ' 0 R >>'
            );

            $w = '';
            foreach (array_keys($used) as $gid) {
                $w .= $gid . ' [' . (int) round($font->advance($gid) * $scale) . '] ';
            }
            $cid = $add(
                '<< /Type /Font /Subtype /CIDFontType2 /BaseFont /' . $base
                . ' /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >>'
                . ' /FontDescriptor ' . $descriptor . ' 0 R /DW 1000 /W [' . trim($w) . '] /CIDToGIDMap /Identity >>'
            );

            $map = '';
            foreach ($used as $gid => $cp) {
                $map .= sprintf('<%04X> <%s>', $gid, strtoupper(bin2hex(mb_convert_encoding(mb_chr($cp, 'UTF-8'), 'UTF-16BE', 'UTF-8')))) . "\n";
            }
            $cmap = "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n"
                . "/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n"
                . "/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n"
                . "1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n"
                . count($used) . " beginbfchar\n" . $map . "endbfchar\n"
                . "endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend";
            $toUnicode = $add($stream('', $cmap));

            $type0 = $add(
                '<< /Type /Font /Subtype /Type0 /BaseFont /' . $base . ' /Encoding /Identity-H'
                . ' /DescendantFonts [' . $cid . ' 0 R] /ToUnicode ' . $toUnicode . ' 0 R >>'
            );
            $fontRefs .= '/F' . $i . ' ' . $type0 . ' 0 R ';
        }
        if ($fontRefs !== '') {
            $resources .= '/Font << ' . $fontRefs . '>> ';
        }

        $body = gzcompress($draw . $this->content, 6);
        $contents = $add($stream('/Filter /FlateDecode', $body));
        $info = $add('<< /Title ' . self::utf16($title) . ' /Producer (Certificates) /CreationDate (D:' . date('YmdHis') . ') >>');

        $objects[$catalog - 1] = '<< /Type /Catalog /Pages ' . $pages . ' 0 R >>';
        $objects[$pages - 1] = '<< /Type /Pages /Kids [' . $page . ' 0 R] /Count 1 >>';
        $objects[$page - 1] = '<< /Type /Page /Parent ' . $pages . ' 0 R /MediaBox [0 0 ' . self::num($this->width) . ' '
            . self::num($this->height) . '] /Resources << ' . $resources . '>> /Contents ' . $contents . ' 0 R >>';

        $out = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $n => $obj) {
            $offsets[$n + 1] = strlen($out);
            $out .= ($n + 1) . " 0 obj\n" . $obj . "\nendobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $off) {
            $out .= sprintf("%010d 00000 n \n", $off);
        }
        $out .= 'trailer << /Size ' . (count($objects) + 1) . ' /Root ' . $catalog . ' 0 R /Info ' . $info . " 0 R >>\n"
            . "startxref\n" . $xref . "\n%%EOF\n";

        return $out;
    }

    private static function num(float $v): string
    {
        $s = number_format($v, 3, '.', '');

        return rtrim(rtrim($s, '0'), '.') ?: '0';
    }

    private static function utf16(string $text): string
    {
        return '<FEFF' . strtoupper(bin2hex(mb_convert_encoding($text, 'UTF-16BE', 'UTF-8'))) . '>';
    }

    private static function subsetTag(string $seed): string
    {
        $hash = md5($seed);
        $tag = '';
        for ($i = 0; $i < 6; $i++) {
            $tag .= chr(65 + hexdec($hash[$i]) % 26);
        }

        return $tag;
    }
}
