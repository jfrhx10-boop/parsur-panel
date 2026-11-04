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
