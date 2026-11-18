<?php
declare(strict_types=1);

/**
 * Потоковая запись ZIP прямо в вывод: файлы не копятся в памяти и на диске.
 * Имена в UTF-8 (флаг 11), без ZIP64 — архив до 4 ГБ.
 */
final class ZipStreamWriter
{
    /** @var list<string> */
    private array $central = [];
    private int $offset = 0;
    private int $dosTime;
    private int $dosDate;

    public function __construct()
    {
        $t = getdate();
        $this->dosTime = ($t['hours'] << 11) | ($t['minutes'] << 5) | intdiv($t['seconds'], 2);
        $this->dosDate = (($t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'];
    }

    public function add(string $name, string $data, bool $compress = true): void
    {
        $crc = crc32($data);
        $method = 0;
        $stored = $data;
        if ($compress) {
            $deflated = gzdeflate($data, 6);
            if ($deflated !== false && strlen($deflated) < strlen($data)) {
                $method = 8;
                $stored = $deflated;
            }
        }
        // Info-ZIP Unicode Path (0x7075): старые распаковщики игнорируют флаг UTF-8, но читают это поле
        $extra = pack('vvC', 0x7075, 5 + strlen($name), 1) . pack('V', crc32($name)) . $name;
        $common = pack('vvvvvVVVvv', 20, 0x0800, $method, $this->dosTime, $this->dosDate, $crc, strlen($stored), strlen($data), strlen($name), strlen($extra));
        $local = "PK\x03\x04" . $common . $name . $extra;
        echo $local, $stored;
        flush();
        // «создан в Unix»: иначе unzip перекодирует имена из DOS-кодировки
        $this->central[] = "PK\x01\x02" . pack('v', 0x0314) . $common . pack('vvvVV', 0, 0, 0, 0100644 << 16, $this->offset) . $name . $extra;
        $this->offset += strlen($local) + strlen($stored);
    }

    public function finish(): void
    {
        $dir = implode('', $this->central);
        $n = count($this->central);
        echo $dir, "PK\x05\x06", pack('vvvvVVv', 0, 0, $n, $n, strlen($dir), $this->offset, 0);
        flush();
    }
}

/**
 * Чтение файлов из ZIP (нужно для .xlsx) без расширения zip.
 *
 * @return array<string, string> имя => содержимое (только запрошенные имена или подходящие под маску)
 */
function zip_read_entries(string $zip, callable $want): array
{
    $eocd = strrpos($zip, "PK\x05\x06");
    if ($eocd === false || strlen($zip) < $eocd + 22) {
        throw new RuntimeException('Файл повреждён или это не архив.');
    }
    $e = unpack('vdisk/vcdisk/ventries/vtotal/Vsize/Voffset', substr($zip, $eocd + 4, 16));
    $pos = (int) $e['offset'];
    $out = [];
    for ($i = 0; $i < (int) $e['total']; $i++) {
        if (substr($zip, $pos, 4) !== "PK\x01\x02") {
            throw new RuntimeException('Файл повреждён.');
        }
        $c = unpack('vver/vneed/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vsize/vnlen/velen/vclen/vdisk/vint/Vext/Vlocal', substr($zip, $pos + 4, 42));
        $name = substr($zip, $pos + 46, (int) $c['nlen']);
        $pos += 46 + (int) $c['nlen'] + (int) $c['elen'] + (int) $c['clen'];
        if (!$want($name)) {
            continue;
        }
        $lh = unpack('vnlen/velen', substr($zip, (int) $c['local'] + 26, 4));
        $dataPos = (int) $c['local'] + 30 + (int) $lh['nlen'] + (int) $lh['elen'];
        $raw = substr($zip, $dataPos, (int) $c['csize']);
        if ((int) $c['method'] === 0) {
            $out[$name] = $raw;
        } elseif ((int) $c['method'] === 8) {
            $data = @gzinflate($raw);
            if ($data === false) {
                throw new RuntimeException('Не удалось распаковать файл.');
            }
            $out[$name] = $data;
        }
    }

    return $out;
}
