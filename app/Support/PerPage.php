<?php

namespace App\Support;

use Illuminate\Http\Request;

/** Documentos por página en las listas: ?n= o lo último elegido (cookie «pp», la pone el selector de la lista). */
class PerPage
{
    public const OPTIONS = [30, 60, 100];

    public static function get(Request $request): int
    {
        $n = (int) ($request->query('n') ?: ($_COOKIE['pp'] ?? 60));

        return in_array($n, self::OPTIONS, true) ? $n : 60;
    }
}
