<?php

if (! function_exists('eur')) {
    /** 1234.5 → "1.234,50 €" */
    function eur($n): string
    {
        return number_format((float) $n, 2, ',', '.').' €';
    }
}

if (! function_exists('fecha')) {
    /** Fecha de WooCommerce (hora local de la tienda) → "04/11/2026 09:30" */
    function fecha(?string $iso, bool $hora = true): string
    {
        if (! $iso) {
            return '';
        }

        return \Carbon\Carbon::parse($iso, 'Europe/Madrid')->format($hora ? 'd/m/Y H:i' : 'd/m/Y');
    }
}

if (! function_exists('estado_pedido')) {
    function estado_pedido(string $s): string
    {
        return \App\Http\Controllers\OrdersController::STATUSES[$s] ?? $s;
    }
}

if (! function_exists('parse_num')) {
    /** "1.234,56", "1234,56", "307.72" o "307,72" → número. Con coma, los puntos son de millares; sin coma, el punto es decimal. */
    function parse_num(?string $s): ?float
    {
        $s = trim(str_replace(['€', ' ', "\u{a0}"], '', (string) $s));
        if ($s === '') {
            return null;
        }
        if (str_contains($s, ',')) {
            $s = str_replace(',', '.', str_replace('.', '', $s));
        } elseif (substr_count($s, '.') > 1) {
            $s = str_replace('.', '', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }
}
