<?php

namespace App\Services;

/**
 * Lee el texto libre de "Observaciones" de FactuSOL y lo separa en:
 *  - máquinas (marca, modelo, nº serie, año, piezas y filtros apuntados),
 *  - piezas sueltas (sin máquina identificable),
 *  - observaciones (todo lo demás: avisos de cobro, facturación, etc.).
 * No adivina: lo dudoso (sin marca o sin nº de serie) queda con review = true.
 */
class FleetParser
{
    private const BRANDS = [
        'NEW HOLLAND', 'JOHN DEERE', 'MASSEY FERGUSON', 'VOLVO PENTA', 'TEREX FERMEC', 'CASE IH', 'CATERPILLAR',
        'VOLVO', 'CAT', 'KOMATSU', 'HITACHI', 'JCB', 'CASE', 'LIEBHERR', 'BOBCAT', 'KUBOTA', 'HYUNDAI', 'DOOSAN',
        'MANITOU', 'TAKEUCHI', 'YANMAR', 'TEREX', 'FIATALLIS', 'FIAT', 'KOBELCO', 'SANY', 'DYNAPAC', 'BOMAG', 'HAMM',
        'AMMANN', 'ATLAS', 'MERLO', 'SDLG', 'LONKING', 'LIUGONG', 'XCMG', 'SUNWARD', 'WACKER', 'FORD', 'FERMEC',
        'SAMSUNG', 'DAEWOO', 'ZETOR', 'HIDROMEK', 'MECALAC', 'NEUSON', 'KRAMER', 'SENNEBOGEN', 'FUCHS', 'JLG',
        'GENIE', 'HAULOTTE', 'DEUTZ', 'SCANIA', 'IVECO', 'MERCEDES', 'RENAULT', 'NISSAN', 'DAF', 'PERKINS',
        'CUMMINS', 'MITSUBISHI', 'ISUZU', 'CLARK', 'DRESSER', 'ORENSTEIN', 'O&K', 'SCHAEFF', 'HANOMAG', 'EBRO',
        'BARREIROS', 'PEGASO', 'BENATI', 'GRADALL', 'KATO', 'IHI', 'BELL', 'MAN', 'LINDE', 'TOYOTA', 'HYSTER',
    ];

    /** Marcas de martillos / implementos que aparecen tras la palabra MARTILLO. */
    private const HAMMER_BRANDS = ['CHICAGO NEWMAN', 'ATLAS COPCO', 'NPK', 'JCB', 'HCO', 'ROTAIR', 'MONTABERT', 'INDECO', 'KRUPP', 'FURUKAWA', 'RAMMER', 'STANLEY', 'EPIROC', 'OKADA', 'SOOSAN', 'TEISAKA', 'HYUNDAI'];

    /** Palabras que delatan que una línea habla de piezas, filtros o desgaste. */
    private const PART_WORDS = '/\b(F\s?(ENG|FUEL|AIR|AIRE|ACEITE|CHO|HCO|HID|TAZA)|FFUEL|FILTRO|FILTER|AIR\s?[12]?[º°]?|ENG\s?OIL|FUEL|ACEITE|HCO|COOLANT|CANT|EDGE|PUNTA|PUNTAS|CUCHILLA|CUCHILLAS|PIN|RIPPER|BOLT|NUT|TORNILLO|TUERCA|PROTECTOR|BELT|TIP|RET|PUNTERO|CADENA|RODILLO|RUEDA|ZAPATA|DIENTE|DIENTES|CAZO|ADAPTADOR|SOBRECANT|CORREA|LATERAL|KIT)\b/i';

    /** Códigos de filtro estilo HIFI / Donaldson: SO115, SN039, SA17118, SH…, P175112 */
    private const FILTER_CODE = '/\b(S[ONAH]\s?\d{3,6}|P\d{6})\b/i';

    /**
     * @return array{machines: array<int,array>, loose: array<int,string>, obs: array<int,string>}
     */
    public static function parse(?string $text): array
    {
        $machines = [];
        $loose = [];
        $obs = [];
        $last = null; // índice de la última máquina

        $text = str_replace(["\r\n", "\r"], "\n", (string) $text);
        foreach (explode("\n", $text) as $raw) {
            $line = trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $raw));
            if ($line === '' || preg_match('/^sin observaciones/i', $line)) {
                continue;
            }
            $continuation = (bool) preg_match('/^[ \t\x{00A0}]/u', $raw);

            if ($m = self::machineFromLine($line)) {
                $machines[] = $m;
                $last = count($machines) - 1;

                continue;
            }

            if (self::looksLikeParts($line)) {
                if ($last !== null) {
                    $machines[$last]['parts'] = trim($machines[$last]['parts'].' · '.$line, ' ·');
                } else {
                    $loose[] = $line;
                }

                continue;
            }

            // Línea tras una máquina con sangría o muy corta sin palabras de aviso: la tratamos como pieza.
            if ($continuation && $last !== null && !preg_match('/[a-záéíóúñ]{5,}\s+[a-záéíóúñ]{3,}\s+[a-záéíóúñ]{3,}/i', $line)) {
                $machines[$last]['parts'] = trim($machines[$last]['parts'].' · '.$line, ' ·');

                continue;
            }

            $obs[] = $line;
        }

        return ['machines' => $machines, 'loose' => $loose, 'obs' => $obs];
    }

    private static function looksLikeParts(string $line): bool
    {
        if (preg_match(self::FILTER_CODE, $line)) {
            return true;
        }
        if (preg_match(self::PART_WORDS, $line) && preg_match('/\d{3,}|[A-Z]{1,3}\d{3,}/', $line)) {
            return true;
        }

        return false;
    }

    private const EQUIPMENT = ['MOLINO', 'CRIBA', 'MACHACADORA', 'GRUPO', 'PLANTA', 'GENERADOR', 'COMPRESOR', 'CINTA', 'LAVADORA', 'TROMEL', 'BOMBA', 'TRITURADORA', 'PRECRIBADOR', 'ALIMENTADOR', 'CAMION', 'CAMIÓN', 'FURGONETA', 'TRACTOR', 'DUMPER', 'CARRETILLA', 'MANIPULADORA', 'PERFORADORA', 'FRESADORA', 'RODILLO'];

    /** @return array<string,mixed>|null */
    private static function machineFromLine(string $line): ?array
    {
        // Equipo fijo con sus filtros: "FILTROS MOLINO DE CONO SH 84129, SH84057…" → equipo "Molino de cono"
        if (preg_match('/^FILTROS?\s+((?:(?:HCO|RETORNO|ACEITE|AIRE|FUEL|DE)\s+)*)((?:[A-ZÁÉÍÓÚÑ]+\s+){0,3}?(?:'.implode('|', self::EQUIPMENT).')(?:\s+(?:DE\s+)?[A-ZÁÉÍÓÚÑ]{2,})?)\s+(.+)$/iu', $line, $m)) {
            $name = implode(' ', array_map(fn ($w) => mb_strlen($w) <= 3 && $w !== 'de' ? strtoupper($w) : $w, explode(' ', mb_strtolower(trim($m[2])))));
            $prefix = trim(preg_replace('/\bDE\b/i', '', $m[1]));

            return self::build('Equipo', '', ucfirst($name), '', trim('FILTROS '.$prefix.' '.$m[3]), true);
        }

        // "MAQUINA CHINA CADENA GL1807237 FENG OIL T501 FFUEL RT2" → máquina "China cadena" (a revisar)
        if (preg_match('/^M[ÁA]QUINA\s+((?:[A-ZÁÉÍÓÚÑ]+\s*){1,3}?)(?=\s+[A-Z]*\d|$)(.*)$/iu', $line, $m)) {
            return self::build(null, '', ucfirst(mb_strtolower(trim($m[1]))), '', trim($m[2]), true);
        }

        // Motor suelto: "MOTOR SCANIA DC13 073A - 6816277 F ENG SO111…"
        if (preg_match('/^MOTOR\s+([A-Z&]+)\s+(.+)$/i', $line, $m) && self::isBrand($m[1])) {
            [$model, $serial, $rest] = self::engineSplit($m[2]);

            return self::build('Motor', $m[1], $model, $serial, $rest, !$serial);
        }

        // Martillo: "MARTILLO NPK H-3XA serie 23294", "DOS MARTILLO HCO TS S330NC - S3301…"
        if (preg_match('/^(?:(?:DOS|TRES|2|3)\s+)?MARTILLOS?\s+(.+)$/i', $line, $m)) {
            $body = $m[1];
            $brand = '';
            foreach (self::HAMMER_BRANDS as $hb) {
                if (stripos($body, $hb.' ') === 0) {
                    $brand = $hb;
                    $body = trim(substr($body, strlen($hb)));
                    break;
                }
            }
            if ($brand === '' && preg_match('/^([A-Za-z]+)\s+(.+)$/', $body, $mm)) {
                $brand = $mm[1];
                $body = $mm[2];
            }
            [$model, $serial, $rest] = self::hammerSplit($body);

            return self::build('Martillo', ucwords(strtolower($brand)) === strtoupper($brand) ? $brand : ucwords(strtolower($brand)), $model, $serial, $rest, true);
        }

        // Máquina normal: [MARCA] MODELO - SERIE [resto]
        $brand = '';
        $body = $line;
        $upper = strtoupper($line);
        foreach (self::brandsLongestFirst() as $b) {
            if (str_starts_with($upper, $b) && preg_match('/^[\s\-\/]/', substr($upper, strlen($b)).' ')) {
                $brand = $b;
                $body = ltrim(substr($line, strlen($b)), " -/\t");
                break;
            }
        }

        if (!preg_match('/^[A-Z0-9]/i', $body)) {
            return null;
        }
        // Solo un modelo (p. ej. "924G"): máquina sin nº de serie, a revisar.
        if (preg_match('/^(?=[A-Z0-9\-]*\d)(?=[A-Z0-9\-]*[A-Z])[A-Z0-9\-]{3,8}$/i', $body) && !preg_match(self::FILTER_CODE, $body)) {
            return self::build(null, $brand, $body, '', '', true);
        }
        [$model, $serial, $rest] = self::modelSerialRest($body, false);
        if ($model === '' || $serial === '') {
            return null; // sin modelo + serie claros, no es línea de máquina
        }
        // El modelo debe tener al menos un dígito (EC360B, D8N, 7050DT) para no confundir frases con máquinas.
        if (!preg_match('/\d/', $model)) {
            return null;
        }
        // Un "modelo" que es un código de filtro (SO287, SA17118) no es una máquina.
        if (preg_match(self::FILTER_CODE, $model)) {
            return null;
        }

        return self::build(null, $brand, $model, $serial, $rest, $brand === '');
    }

    /**
     * @return array{0:string,1:string,2:string} modelo, serie, resto
     */
    private static function modelSerialRest(string $s, bool $serialOptional): array
    {
        $s = trim($s);
        // Modelo: p. ej. EC360B, D8N, 7050DT, ZW310-6, BF4M 1012C, 966GII, H-3XA, TS S330NC
        $re = '/^(?<model>[A-Z]{0,4}\d{1,4}[A-Z0-9]{0,4}(?:-\d{1,2}(?=\s|$))?(?:\s[A-Z0-9]{2,5}(?=\s*(?:[-–\/]|serie|s\/n|nº|n°|$)))?|[A-Z]{1,3}-\d[A-Z0-9]{1,4})\s*(?:[-–\/]|\bserie\b|\bs\/n\b|\bnº\b|\bn°\b|\s)\s*(?<serial>[A-Z0-9][A-Z0-9\-]{3,})(?<rest>.*)$/iu';
        if (preg_match($re, $s, $m)) {
            return [trim($m['model']), trim($m['serial'], ' -'), trim($m['rest'])];
        }
        if ($serialOptional && preg_match('/^(?<model>.+?)(?:\s*[-–\/]\s*(?<serial>[A-Z0-9]{4,}))?(?<rest>\s+F\s.*)?$/iu', $s, $m)) {
            return [trim($m['model']), trim($m['serial'] ?? ''), trim($m['rest'] ?? '')];
        }

        return [$s, '', ''];
    }

    /** @return array{0:string,1:string,2:string} */
    private static function engineSplit(string $s): array
    {
        if (preg_match('/^(?<model>.+?)\s*[-–\/]\s*(?<serial>[A-Z0-9]{4,})(?<rest>\s.*)?$/iu', trim($s), $m)) {
            return [trim($m['model']), trim($m['serial']), trim($m['rest'] ?? '')];
        }

        return [trim($s), '', ''];
    }

    /** @return array{0:string,1:string,2:string} */
    private static function hammerSplit(string $s): array
    {
        $s = trim($s);
        if (preg_match('/^(?<model>.+?)\s*(?:[-–\/]|\bserie\b|\bs\/n\b)\s*(?<serial>[A-Z0-9][A-Z0-9\-]{3,})(?<rest>\s.*)?$/iu', $s, $m)) {
            return [trim($m['model']), trim($m['serial']), trim($m['rest'] ?? '')];
        }

        return [$s, '', ''];
    }

    /** @return array<string,mixed> */
    private static function build(?string $type, string $brand, string $model, string $serial, string $rest, bool $review): array
    {
        $year = null;
        if (preg_match('/\bA[ÑN]O\s*(\d{4})\b/iu', $rest, $y)) {
            $year = (int) $y[1];
            $rest = trim(str_replace($y[0], '', $rest));
        }
        $brand = trim($brand);
        $brand = $brand === '' ? '' : (strlen($brand) <= 3 ? strtoupper($brand) : ucwords(strtolower($brand)));
        $model = trim($model);
        if (preg_match('/\d/', $model) && !preg_match('/\s[a-záéíóúñ]/u', $model)) {
            $model = strtoupper($model);
        }
        $type = $type ?? self::guessType($brand, $model);

        return [
            'type' => $type,
            'brand' => $brand,
            'model' => $model,
            'serial' => $serial !== '' ? strtoupper($serial) : null,
            'year' => $year,
            'parts' => trim(preg_replace('/\s+/', ' ', $rest), " ·-|"),
            'review' => $review || $serial === '',
        ];
    }

    private static function guessType(string $brand, string $model): ?string
    {
        $b = strtoupper($brand);
        if (preg_match('/^(EC|ECR|EW|ZX|EX|PC|SK|R\d)\d/i', $model) || preg_match('/^3\d\d[A-Z]{0,3}$/', $model) && in_array($b, ['CAT', 'CATERPILLAR', ''], true)) {
            return 'Excavadora';
        }
        if (preg_match('/^(9\d\d[A-Z]{0,3}|L\d{2,3}[A-Z]?|ZW\d+|WA\d+|LW\d+)/i', $model)) {
            return 'Pala cargadora';
        }
        if (preg_match('/^A\d{2}[A-Z]?$/', $model) && in_array($b, ['VOLVO', ''], true)) {
            return 'Dumper';
        }
        if (preg_match('/^D\d{1,2}[A-Z]?$/', $model) && in_array($b, ['CAT', 'CATERPILLAR', 'KOMATSU', ''], true)) {
            return 'Bulldozer';
        }
        if (preg_match('/^1[0-9]{1,2}[GHKM]$/', $model) && in_array($b, ['CAT', 'CATERPILLAR', ''], true)) {
            return 'Motoniveladora';
        }

        return null;
    }

    private static function isBrand(string $w): bool
    {
        return in_array(strtoupper($w), self::BRANDS, true);
    }

    /** @return array<int,string> */
    private static function brandsLongestFirst(): array
    {
        static $list = null;
        if ($list === null) {
            $list = self::BRANDS;
            usort($list, fn ($a, $b) => strlen($b) <=> strlen($a));
        }

        return $list;
    }
}
