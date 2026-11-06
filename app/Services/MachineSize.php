<?php

namespace App\Services;

/**
 * Tonelaje aproximado de una máquina a partir de marca, modelo y tipo, y su tamaño (chica / media / grande).
 * Casi todos los fabricantes codifican el peso operativo en el modelo (ZX250 → 25 t, EC240 → 24 t, PC200 → 20 t…).
 * Es una estimación: en la ficha se puede corregir a mano.
 */
class MachineSize
{
    public const SMALL_MAX = 8;   // < 8 t  → chica

    public const MEDIUM_MAX = 20; // 8–20 t → media; > 20 t → grande

    public static function sizeOf(?float $tons): ?string
    {
        if ($tons === null) {
            return null;
        }
        if ($tons < self::SMALL_MAX) {
            return 'Chica';
        }
        if ($tons <= self::MEDIUM_MAX) {
            return 'Media';
        }

        return 'Grande';
    }

    public static function label(?float $tons): ?string
    {
        $s = self::sizeOf($tons);

        return $s ? $s.' · ~'.self::fmt($tons).' t' : null;
    }

    public static function fmt(float $t): string
    {
        return $t == (int) $t ? (string) (int) $t : number_format($t, 1, ',', '');
    }

    /** Estimación en toneladas, o null si no se puede deducir. */
    public static function tons(?string $brand, ?string $model, ?string $type = null): ?float
    {
        $b = strtoupper(trim((string) $brand));
        $m = strtoupper(trim((string) $model));
        $m = preg_replace('/\s+(PRO|PLUS|LC|NLC|SR|ZTS|TURBO|HD|XL|XT|CR|4PT|4WD|SERIE\s+[IVX]+)$/', '', $m);
        if ($m === '') {
            return null;
        }
        $t = strtolower((string) $type);

        // Motores y martillos no van por tonelaje de máquina
        if (str_contains($t, 'motor') || str_contains($t, 'martillo') || preg_match('/^MOTOR\b/', $m)) {
            return null;
        }

        $num = static function (string $re, string $s): ?float {
            return preg_match($re, $s, $x) ? (float) $x[1] : null;
        };

        // ---------- Excavadoras (el número suele ser el peso en décimas de tonelada: ZX250 = 25 t)
        if (preg_match('/^(ZX|ZAXIS|EX)(\d{2,3})/', $m, $x)) { // Hitachi
            return (int) $x[2] / 10;
        }
        if (preg_match('/^(EC|ECR|EW|EWR)(\d{2,3})/', $m, $x)) { // Volvo
            return (int) $x[2] / 10;
        }
        if (preg_match('/^(PC|PW)(\d{2,4})/', $m, $x)) { // Komatsu
            return (int) $x[2] / 10;
        }
        if (preg_match('/^(JS|JZ)(\d{2,3})/', $m, $x)) { // JCB
            return (int) $x[2] / 10;
        }
        if (preg_match('/^(R)(\d{3})/', $m, $x) && $b === 'LIEBHERR') { // Liebherr R904 ≈ 22 t, R924 ≈ 25 t, R944 ≈ 40 t
            $n = (int) $x[2];
            $map = [900 => 18, 904 => 22, 906 => 23, 914 => 16, 916 => 20, 918 => 21, 920 => 22, 922 => 23, 924 => 25, 926 => 28, 934 => 33, 936 => 35, 944 => 40, 946 => 42, 954 => 50, 956 => 56, 964 => 65, 966 => 70, 974 => 75, 976 => 80, 984 => 100];
            if (isset($map[$n])) {
                return (float) $map[$n];
            }
        }
        if (preg_match('/^(?:ROBEX\s*)?(?:R|HX|HW)?(\d{2,3})[A-Z]/', $m, $x) && in_array($b, ['HYUNDAI'], true)) { // Hyundai R140W = 14 t, ROBEX 170W = 17 t
            return (int) $x[1] / 10;
        }
        if (preg_match('/^(R|HX|HW)(\d{2,3})/', $m, $x) && $b === '') {
            return (int) $x[2] / 10;
        }
        if (preg_match('/^(DX|DH|SOLAR\s*|S)(\d{2,3})/', $m, $x) && in_array($b, ['DOOSAN', 'DEVELON', 'DAEWOO'], true)) {
            return (int) $x[2] / 10;
        }
        if (preg_match('/^(SK)(\d{2,3})/', $m, $x)) { // Kobelco
            return (int) $x[2] / 10;
        }
        if (preg_match('/^(CX)(\d{2,3})/', $m, $x) && $b === 'CASE') { // Case excavadoras
            return (int) $x[2] / 10;
        }
        if (preg_match('/^(E)(\d{2,3})/', $m, $x) && in_array($b, ['NEW HOLLAND', 'BOBCAT', 'KOBELCO', ''], true)) { // NH E305 = 30 t; Bobcat E60 = 6 t
            return (int) $x[2] / 10;
        }
        if (preg_match('/^(SE)(\d{2,3})/', $m, $x) && $b === 'SAMSUNG') {
            return (int) $x[2] / 10;
        }
        if (preg_match('/^(SWE)\s*(\d{2,3})/', $m, $x)) { // Sunward
            return (int) $x[2] / 10;
        }
        if (preg_match('/^(KX|U)(\d{2,3})/', $m, $x) && in_array($b, ['KUBOTA', ''], true)) { // Kubota KX080 = 8 t, KX057 = 5,7 t, U55 = 5,5 t
            $n = (int) $x[2];

            return $n >= 100 ? $n / 100 : $n / 10;
        }
        if (preg_match('/^(TB)(\d{3})/', $m, $x) && in_array($b, ['TAKEUCHI', ''], true)) { // TB135 = 3,5 t; TB153 = 5,3 t; TB2150 = 15 t
            $n = (int) $x[2];

            return $n >= 1000 ? ($n % 1000) / 10 : ($n >= 200 ? ($n % 100) : ($n % 100) / 10);
        }
        if (preg_match('/^(\d{3})([A-Z]{0,3})(-|\s|$|\d)/', $m, $x) && in_array($b, ['CAT', 'CATERPILLAR', ''], true)) { // CAT 3xx = excavadoras (320 → 20 t); 9xx palas; 4xx retros
            $n = (int) $x[1];
            if ($n >= 300 && $n < 400) {
                return ($n - 300) ? (float) ($n - 300) : 8.0; // 300.9 → 1 t… (301, 303…); 320 → 20
            }
            if ($n >= 900 && $n < 1000) {
                $map = [902 => 5, 903 => 5.5, 906 => 6, 907 => 6.5, 908 => 8, 910 => 11, 914 => 8, 920 => 12, 924 => 12, 926 => 13, 928 => 13, 930 => 14, 938 => 15, 950 => 19, 962 => 20, 966 => 23, 972 => 25, 980 => 30, 982 => 36, 988 => 50, 990 => 80, 992 => 100];

                return isset($map[$n]) ? (float) $map[$n] : null;
            }
            if ($n >= 400 && $n < 500) { // retroexcavadoras CAT 4xx
                return 8.5;
            }
            if ($n >= 200 && $n < 300) { // minicargadoras / compactas CAT 2xx
                return 3.0;
            }
            if ($n >= 100 && $n < 200) { // motoniveladoras 120M, 140M, 160M
                $map = [120 => 14, 12 => 14, 135 => 15, 140 => 17, 14 => 17, 143 => 17, 160 => 19, 16 => 19, 24 => 60];

                return isset($map[$n]) ? (float) $map[$n] : 15.0;
            }
        }
        if (preg_match('/^(12|14|16)[GHKM]$/', $m) && in_array($b, ['CAT', 'CATERPILLAR', ''], true)) { // 12G, 14H, 16M
            return [12 => 14.0, 14 => 17.0, 16 => 19.0][(int) substr($m, 0, 2)];
        }
        if (preg_match('/^D(\d{1,2})[A-Z]?/', $m, $x) && in_array($b, ['CAT', 'CATERPILLAR', ''], true)) { // bulldozers D3…D11
            $map = [3 => 8, 4 => 10, 5 => 14, 6 => 20, 7 => 27, 8 => 38, 9 => 48, 10 => 66, 11 => 100];

            return isset($map[(int) $x[1]]) ? (float) $map[(int) $x[1]] : null;
        }
        if (preg_match('/^D(\d{2,3})/', $m, $x) && $b === 'KOMATSU') { // D61 ≈ 17 t, D65 ≈ 20 t, D155 ≈ 40 t
            $n = (int) $x[1];

            return $n >= 100 ? $n / 4 : $n / 3.3;
        }

        // ---------- Palas cargadoras
        if (preg_match('/^L(\d{2,3})/', $m, $x) && in_array($b, ['VOLVO', ''], true)) { // Volvo L60 ≈ 11 t, L90 ≈ 15, L120 ≈ 19, L150 ≈ 24, L180 ≈ 28, L220 ≈ 32
            $map = [20 => 4.5, 25 => 5, 30 => 6, 35 => 6.5, 45 => 8.5, 50 => 9.5, 60 => 11, 70 => 13, 90 => 15, 110 => 18, 120 => 19, 150 => 24, 180 => 28, 220 => 32, 250 => 35, 260 => 36, 350 => 52];

            return isset($map[(int) $x[1]]) ? (float) $map[(int) $x[1]] : null;
        }
        if (preg_match('/^(ZW)(\d{2,3})/', $m, $x)) { // Hitachi ZW: 220 ≈ 18 t, 310 ≈ 24 t
            $map = [65 => 5, 75 => 5.5, 95 => 7, 120 => 10, 140 => 11, 150 => 13, 180 => 15, 220 => 18, 250 => 21, 310 => 24, 330 => 27, 370 => 31, 550 => 50];

            return isset($map[(int) $x[2]]) ? (float) $map[(int) $x[2]] : null;
        }
        if (preg_match('/^(WA)(\d{2,3})/', $m, $x)) { // Komatsu WA: 300 ≈ 11 t, 380 ≈ 18, 470 ≈ 23, 500 ≈ 32
            $map = [70 => 5, 80 => 6, 100 => 7, 200 => 10, 250 => 11, 270 => 12, 300 => 11, 320 => 14, 380 => 18, 420 => 19, 430 => 20, 450 => 22, 470 => 23, 480 => 25, 500 => 32, 600 => 53, 700 => 70];

            return isset($map[(int) $x[2]]) ? (float) $map[(int) $x[2]] : null;
        }
        if (preg_match('/^(W)(\d{2,3})/', $m, $x) && in_array($b, ['NEW HOLLAND', 'CASE', ''], true)) { // NH W190 ≈ 17 t; Case 721 ≈ 13 t
            $map = [50 => 5, 60 => 5.5, 70 => 6, 80 => 6.5, 110 => 11, 130 => 12, 170 => 14, 190 => 17, 230 => 21, 270 => 25, 300 => 30];

            return isset($map[(int) $x[2]]) ? (float) $map[(int) $x[2]] : null;
        }
        if (preg_match('/^(\d{3})[A-Z]?/', $m, $x) && $b === 'CASE') { // Case 521 ≈ 10 t, 621 ≈ 12, 721 ≈ 13, 821 ≈ 17, 921 ≈ 21
            $map = [21 => 4, 121 => 5, 221 => 6, 321 => 7, 421 => 9, 521 => 10, 621 => 12, 721 => 13, 821 => 17, 921 => 21, 1021 => 25, 1121 => 30];
            if (isset($map[(int) $x[1]])) {
                return (float) $map[(int) $x[1]];
            }
        }
        if (preg_match('/^(4\d\d)/', $m, $x) && $b === 'JCB') { // JCB 411 ≈ 8 t, 427 ≈ 12, 437 ≈ 15, 456 ≈ 20
            $map = [403 => 4, 406 => 5, 407 => 5.5, 409 => 6, 411 => 8, 416 => 9, 417 => 10, 426 => 12, 427 => 12, 436 => 14, 437 => 15, 456 => 20, 457 => 20];

            return isset($map[(int) $x[1]]) ? (float) $map[(int) $x[1]] : null;
        }

        // ---------- Retroexcavadoras y mixtas (todas 7–10 t)
        if (preg_match('/^(3CX|4CX|2CX|1CX|3DX|4DX|2DX|5CX)/', $m) || preg_match('/^(580|590|595|695|580SR|590SR|695SR)/', $m) || preg_match('/^(LB|B)(9|1[01])\d/', $m) || preg_match('/^BL(6|7)\d/', $m) || preg_match('/^(WB|TLB)\d{2}/', $m) || preg_match('/^(860|960|970|980)[A-Z]?$/', $m)) {
            return 8.0;
        }
        if (preg_match('/^2DX|^1CX/', $m)) {
            return 4.0;
        }

        // ---------- Minicargadoras / compactas
        if (preg_match('/^(S|T)(\d{3})/', $m, $x) && in_array($b, ['BOBCAT', ''], true)) { // Bobcat S175 ≈ 3 t, T770 ≈ 5 t
            return (int) $x[2] >= 500 ? 5.0 : 3.0;
        }
        if (preg_match('/^(MT|S70|S100|463)/', $m) && $b === 'BOBCAT') {
            return 1.5;
        }

        // ---------- Dumpers articulados (número = carga útil)
        if (preg_match('/^A(\d{2})[A-Z]?/', $m, $x) && in_array($b, ['VOLVO', ''], true)) {
            return (float) $x[1];
        }
        if (preg_match('/^(B|TA)(\d{2})/', $m, $x) && in_array($b, ['BELL', 'TEREX', ''], true)) {
            return (float) $x[2];
        }

        // ---------- Motoniveladoras y compactadores
        if (preg_match('/^(7\d\d)[A-Z]?/', $m, $x) && $b === 'CHAMPION') { // Champion 710/720/730 ≈ 13–15 t
            return 14.0;
        }
        if (preg_match('/^(G)(\d{3})/', $m, $x) && $b === 'VOLVO') { // Volvo G710 ≈ 15 t, G740 ≈ 16, G930 ≈ 16, G960 ≈ 17
            return 16.0;
        }
        if (preg_match('/^(BW)\s?(\d{2,3})/', $m, $x) && $b === 'BOMAG') { // BW 213 ≈ 13 t, BW 177 ≈ 7 t, BW 120 ≈ 2,5 t
            $n = (int) $x[2];

            return $n >= 200 ? (float) ($n - 200) : ($n >= 170 ? 7.0 : 2.5);
        }
        if (preg_match('/^(DTV|DV|C)\s?(\d{3})/', $m, $x) && in_array($b, ['BITELLI', 'DYNAPAC', ''], true)) {
            return 8.0;
        }

        // ---------- Manipuladores telescópicos / carretillas (por capacidad: JCB 532-70 = 3,2 t; Manitou MT 1440 = 4 t)
        if (preg_match('/^(5\d\d)[-\s]?(\d{2})?/', $m, $x) && $b === 'JCB') {
            return 8.0;
        }
        if (preg_match('/^(MT|MLT|MRT)\s?-?(\d{3,4})/', $m) && $b === 'MANITOU') {
            return 7.0;
        }
        if (preg_match('/^H(\d{2,3})/', $m, $x) && $b === 'LINDE') { // Linde H25 = 2,5 t, H250 = 25 t (capacidad)
            $n = (int) $x[1];

            return $n >= 100 ? $n / 10 * 1.3 : $n / 10 * 1.5;
        }

        // ---------- Tractores (no van por tonelaje; ~ 4–8 t)
        if (preg_match('/^(TL|T\d|TM|TS|TD)\d{2}/', $m) && $b === 'NEW HOLLAND') {
            return 5.0;
        }
        if (preg_match('/^\d{4}[A-Z]{0,2}$/', $m) && in_array($b, ['JOHN DEERE', 'KUBOTA', 'MASSEY FERGUSON', 'DEUTZ-FAHR', 'FENDT', 'CASE IH'], true)) {
            return 5.0;
        }

        return null;
    }
}
