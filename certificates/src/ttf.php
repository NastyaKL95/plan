<?php
declare(strict_types=1);

/**
 * Чтение TrueType-шрифта: коды символов, ширины и подмножество глифов для PDF.
 */
final class TtfFont
{
    public string $name;
    public int $unitsPerEm = 1000;
    public int $ascent = 0;
    public int $descent = 0;
    public int $capHeight = 0;
    public float $italicAngle = 0.0;
    /** @var array{0:int,1:int,2:int,3:int} */
    public array $bbox = [0, 0, 0, 0];

    private string $data;
    /** @var array<string, array{0:int,1:int}> */
    private array $tables = [];
    private int $numGlyphs = 0;
    private int $numHMetrics = 0;
    private int $locFormat = 0;
    /** @var array<int, int> */
    private array $cmap = [];
    /** @var array<int, int> */
    private array $advances = [];

    public function __construct(string $path, string $name)
    {
        $data = @file_get_contents($path);
        if ($data === false || strlen($data) < 12) {
            throw new RuntimeException('Не удалось прочитать шрифт ' . basename($path));
        }
        $this->data = $data;
        $this->name = $name;
        $count = $this->u16(4);
        for ($i = 0; $i < $count; $i++) {
            $rec = 12 + $i * 16;
            $this->tables[substr($data, $rec, 4)] = [$this->u32($rec + 8), $this->u32($rec + 12)];
        }
        foreach (['head', 'hhea', 'maxp', 'hmtx', 'loca', 'glyf', 'cmap'] as $tag) {
            if (!isset($this->tables[$tag])) {
                throw new RuntimeException('В шрифте нет таблицы ' . $tag);
            }
        }
        $head = $this->tables['head'][0];
        $this->unitsPerEm = $this->u16($head + 18);
        $this->bbox = [$this->i16($head + 36), $this->i16($head + 38), $this->i16($head + 40), $this->i16($head + 42)];
        $this->locFormat = $this->i16($head + 50);
        $hhea = $this->tables['hhea'][0];
        $this->ascent = $this->i16($hhea + 4);
        $this->descent = $this->i16($hhea + 6);
        $this->numHMetrics = $this->u16($hhea + 34);
        $this->numGlyphs = $this->u16($this->tables['maxp'][0] + 4);
        $this->capHeight = $this->ascent;
        if (isset($this->tables['OS/2'])) {
            [$os2, $len] = $this->tables['OS/2'];
            if ($this->u16($os2) >= 2 && $len >= 90) {
                $this->capHeight = $this->i16($os2 + 88);
            }
        }
        if (isset($this->tables['post'])) {
            $p = $this->tables['post'][0];
            $this->italicAngle = $this->i16($p + 4) + $this->u16($p + 6) / 65536;
        }
        $this->readHmtx();
        $this->readCmap();
    }

    public function glyphId(int $codepoint): int
    {
        return $this->cmap[$codepoint] ?? 0;
    }

    public function advance(int $gid): int
    {
        if ($gid < $this->numHMetrics) {
            return $this->advances[$gid] ?? 0;
        }

        return $this->advances[$this->numHMetrics - 1] ?? 0;
    }

    /** Ширина строки в единицах шрифта. */
    public function textWidth(string $text): int
    {
        $w = 0;
        foreach (mb_str_split($text, 1, 'UTF-8') as $ch) {
            $w += $this->advance($this->glyphId(mb_ord($ch, 'UTF-8')));
        }

        return $w;
    }

    /**
     * Шрифт, в котором оставлены только нужные глифы. Номера глифов не меняются.
     *
     * @param array<int, true> $gids
     */
    public function subset(array $gids): string
    {
        $gids[0] = true;
        $queue = array_keys($gids);
        while ($queue !== []) {
            $gid = array_pop($queue);
            foreach ($this->components($gid) as $c) {
                if (!isset($gids[$c])) {
                    $gids[$c] = true;
                    $queue[] = $c;
                }
            }
        }
        $glyf = '';
        $loca = '';
        for ($g = 0; $g < $this->numGlyphs; $g++) {
            $loca .= pack('N', strlen($glyf));
            if (isset($gids[$g])) {
                $glyph = $this->glyphData($g);
                $glyf .= $glyph . str_repeat("\0", (4 - strlen($glyph) % 4) % 4);
            }
        }
        $loca .= pack('N', strlen($glyf));

        $head = $this->table('head');
        $head = substr_replace($head, "\0\0\0\0", 8, 4);
        $head = substr_replace($head, pack('n', 1), 50, 2);

        $out = [
            'head' => $head,
            'hhea' => $this->table('hhea'),
            'maxp' => $this->table('maxp'),
            'hmtx' => $this->table('hmtx'),
            'loca' => $loca,
            'glyf' => $glyf,
        ];
        foreach (['cvt ', 'fpgm', 'prep'] as $tag) {
            if (isset($this->tables[$tag])) {
                $out[$tag] = $this->table($tag);
            }
        }

        return self::buildSfnt($out);
    }

    /** @param array<string, string> $tables */
    private static function buildSfnt(array $tables): string
    {
        ksort($tables, SORT_STRING);
        $n = count($tables);
        $pow = 1;
        $log = 0;
        while ($pow * 2 <= $n) {
            $pow *= 2;
            $log++;
        }
        $header = pack('Nnnnn', 0x00010000, $n, $pow * 16, $log, $n * 16 - $pow * 16);
        $offset = 12 + 16 * $n;
        $dir = '';
        $body = '';
        foreach ($tables as $tag => $bytes) {
            $len = strlen($bytes);
            $padded = $bytes . str_repeat("\0", (4 - $len % 4) % 4);
            $dir .= $tag . pack('NNN', self::checksum($padded), $offset, $len);
            $body .= $padded;
            $offset += strlen($padded);
        }

        return $header . $dir . $body;
    }

    private static function checksum(string $bytes): int
    {
        $sum = 0;
        foreach (unpack('N*', $bytes) ?: [] as $word) {
            $sum = ($sum + $word) & 0xFFFFFFFF;
        }

        return $sum;
    }

    private function table(string $tag): string
    {
        [$off, $len] = $this->tables[$tag];

        return substr($this->data, $off, $len);
    }

    private function glyphRange(int $gid): array
    {
        $loca = $this->tables['loca'][0];
        if ($this->locFormat === 0) {
            $a = $this->u16($loca + $gid * 2) * 2;
            $b = $this->u16($loca + $gid * 2 + 2) * 2;
        } else {
            $a = $this->u32($loca + $gid * 4);
            $b = $this->u32($loca + $gid * 4 + 4);
        }

        return [$a, $b];
    }

    private function glyphData(int $gid): string
    {
        if ($gid < 0 || $gid >= $this->numGlyphs) {
            return '';
        }
        [$a, $b] = $this->glyphRange($gid);
        if ($b <= $a) {
            return '';
        }

        return substr($this->data, $this->tables['glyf'][0] + $a, $b - $a);
    }

    /** @return list<int> */
    private function components(int $gid): array
    {
        $glyph = $this->glyphData($gid);
        if (strlen($glyph) < 10 || unpack('n', $glyph)[1] < 0x8000) {
            return [];
        }
        $out = [];
        $pos = 10;
        do {
            if ($pos + 4 > strlen($glyph)) {
                break;
            }
            $flags = unpack('n', substr($glyph, $pos, 2))[1];
            $out[] = unpack('n', substr($glyph, $pos + 2, 2))[1];
            $pos += 4 + (($flags & 0x0001) ? 4 : 2);
            if ($flags & 0x0008) {
                $pos += 2;
            } elseif ($flags & 0x0040) {
                $pos += 4;
            } elseif ($flags & 0x0080) {
                $pos += 8;
            }
        } while ($flags & 0x0020);

        return $out;
    }

    private function readHmtx(): void
    {
        $off = $this->tables['hmtx'][0];
        for ($i = 0; $i < $this->numHMetrics; $i++) {
            $this->advances[$i] = $this->u16($off + $i * 4);
        }
    }

    private function readCmap(): void
    {
        $base = $this->tables['cmap'][0];
        $count = $this->u16($base + 2);
        $best = null;
        $bestRank = 0;
        for ($i = 0; $i < $count; $i++) {
            $rec = $base + 4 + $i * 8;
            $platform = $this->u16($rec);
            $encoding = $this->u16($rec + 2);
            $sub = $base + $this->u32($rec + 4);
            $format = $this->u16($sub);
            $rank = 0;
            if ($platform === 3 && $encoding === 10 && $format === 12) {
                $rank = 3;
            } elseif ($platform === 3 && $encoding === 1 && $format === 4) {
                $rank = 2;
            } elseif ($platform === 0 && ($format === 4 || $format === 12)) {
                $rank = 1;
            }
            if ($rank > $bestRank) {
                $bestRank = $rank;
                $best = $sub;
            }
        }
        if ($best === null) {
            throw new RuntimeException('В шрифте нет таблицы Unicode');
        }
        if ($this->u16($best) === 12) {
            $groups = $this->u32($best + 12);
            for ($g = 0; $g < $groups; $g++) {
                $p = $best + 16 + $g * 12;
                $start = $this->u32($p);
                $end = $this->u32($p + 4);
                $glyph = $this->u32($p + 8);
                if ($end - $start > 0x10000) {
                    continue;
                }
                for ($c = $start; $c <= $end; $c++) {
                    $this->cmap[$c] = $glyph + ($c - $start);
                }
            }

            return;
        }
        $segX2 = $this->u16($best + 6);
        $ends = $best + 14;
        $starts = $ends + $segX2 + 2;
        $deltas = $starts + $segX2;
        $ranges = $deltas + $segX2;
        for ($s = 0; $s < $segX2 / 2; $s++) {
            $end = $this->u16($ends + $s * 2);
            $start = $this->u16($starts + $s * 2);
            $delta = $this->i16($deltas + $s * 2);
            $rangeOff = $this->u16($ranges + $s * 2);
            if ($start === 0xFFFF) {
                continue;
            }
            for ($c = $start; $c <= $end; $c++) {
                if ($rangeOff === 0) {
                    $g = ($c + $delta) & 0xFFFF;
                } else {
                    $addr = $ranges + $s * 2 + $rangeOff + 2 * ($c - $start);
                    $g = $this->u16($addr);
                    if ($g !== 0) {
                        $g = ($g + $delta) & 0xFFFF;
                    }
                }
                if ($g !== 0) {
                    $this->cmap[$c] = $g;
                }
            }
        }
    }

    private function u16(int $o): int
    {
        return unpack('n', $this->data, $o)[1];
    }

    private function i16(int $o): int
    {
        $v = $this->u16($o);

        return $v >= 0x8000 ? $v - 0x10000 : $v;
    }

    private function u32(int $o): int
    {
        return unpack('N', $this->data, $o)[1];
    }
}
