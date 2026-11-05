<?php

namespace App\Services;

/**
 * Iconos de línea (SVG inline, 24x24, trazo currentColor) para máquinas y piezas.
 * Todos comparten estilo: trazo 1.7, esquinas redondeadas, sin relleno.
 */
class Icons
{
    private const PATHS = [
        // ---- máquinas ----
        'excavadora' => '<rect x="3" y="17" width="12" height="4" rx="2"/><path d="M5 17v-5h8v5"/><path d="M6 12V9h4v3"/><path d="M13 13l5-8 3 2-4 6"/><path d="M17 13l3 3-3 1-2-2"/>',
        'miniexcavadora' => '<rect x="4" y="16" width="10" height="4" rx="2"/><path d="M6 16v-4h6v4"/><path d="M7 12V9h3v3"/><path d="M12 13l4-6 2.5 1.5-3 5"/><path d="M15.5 13.5l2.5 2.5-2.5 1-1.5-1.5"/><path d="M2 17h2"/>',
        'pala' => '<circle cx="7" cy="17" r="2.5"/><circle cx="16" cy="17" r="2.5"/><path d="M9.5 17h4"/><path d="M5 14.5v-3h6v3"/><path d="M6 11.5V8h4v3.5"/><path d="M11 12h4l4 3"/><path d="M19 15v4h-3"/>',
        'bulldozer' => '<rect x="3" y="15" width="11" height="5" rx="2"/><path d="M5 15v-4h7v4"/><path d="M6 11V8h4v3"/><path d="M14 15h2"/><path d="M19 11v9"/><path d="M16 13l3-2"/><path d="M16 17l3 1"/>',
        'dumper' => '<circle cx="5" cy="18" r="2"/><circle cx="12" cy="18" r="2"/><circle cx="19" cy="18" r="2"/><path d="M3 15v-5h5v5"/><path d="M8 15h1"/><path d="M9 15l1-6h11l1 6"/>',
        'motoniveladora' => '<circle cx="5" cy="17" r="2"/><circle cx="18" cy="17" r="2"/><path d="M7 14h14v-4"/><path d="M15 10V7h-4v3"/><path d="M7 14l-3-4h7"/><path d="M8 12l3 3"/><path d="M6 15h5"/>',
        'compactador' => '<path d="M3 19h18"/><circle cx="7" cy="15" r="3"/><circle cx="17" cy="15" r="3"/><path d="M10 15h4V9h-5v6"/><path d="M12 9V7"/>',
        'tractor' => '<circle cx="7" cy="16" r="4"/><circle cx="18" cy="18" r="2"/><path d="M11 16h5v-5h-4V8H8v4"/><path d="M12 11h4"/>',
        'motor' => '<rect x="4" y="9" width="13" height="9" rx="1.5"/><path d="M8 9V6h5v3"/><path d="M17 12h3v3h-3"/><path d="M2 12v3"/><path d="M7 13h7"/><path d="M7 15.5h4"/>',
        'martillo' => '<path d="M9 3h6l1 3v6H8V6z"/><path d="M11 12v6h2v-6"/><path d="M10 18h4v3h-4z"/><path d="M8 7h8"/>',
        'equipo' => '<path d="M4 20h16"/><path d="M6 20V9l6-5 6 5v11"/><path d="M10 20v-6h4v6"/><path d="M9 11h6"/>',
        'maquina' => '<rect x="3" y="15" width="10" height="5" rx="1.5"/><path d="M5 15v-4h6v4"/><path d="M13 17h3l4-3"/><path d="M20 14v4h-3"/><path d="M6 11V8h4v3"/>',
        'piezas' => '<rect x="3" y="8" width="18" height="12" rx="1.5"/><path d="M8 8V5h8v3"/><path d="M3 13h18"/>',
        // ---- piezas ----
        'filtro' => '<rect x="7" y="4" width="10" height="16" rx="5"/><path d="M7 9h10"/><path d="M7 15h10"/><path d="M12 4v16"/>',
        'cuchilla' => '<path d="M3 10h18v4l-2 4H5l-2-4z"/><circle cx="8" cy="12" r="1"/><circle cx="16" cy="12" r="1"/>',
        'cantonera' => '<path d="M4 5v15h15"/><path d="M4 5l4 4v7h7l4 4"/><circle cx="9" cy="14" r="1"/>',
        'punta' => '<path d="M8 4h8v6l-4 10-4-10z"/><path d="M8 7h8"/>',
        'tornillo' => '<path d="M9 3h6v4H9z"/><path d="M11 7v13"/><path d="M13 7v13"/><path d="M11 10h2"/><path d="M11 13h2"/><path d="M11 16h2"/>',
        'pasador' => '<rect x="3" y="10" width="18" height="4" rx="2"/><path d="M7 10V8"/><path d="M7 14v2"/>',
        'correa' => '<ellipse cx="12" cy="12" rx="9" ry="5"/><ellipse cx="12" cy="12" rx="5" ry="2.5"/>',
        'ripper' => '<path d="M8 4h8v3H8z"/><path d="M12 7v6c0 3 2 5 5 7"/><path d="M17 20l-2-3"/>',
        'protector' => '<path d="M12 3l8 3v6c0 5-4 8-8 9-4-1-8-4-8-9V6z"/><path d="M12 7v10"/>',
        'grasa' => '<path d="M9 3h6v5H9z"/><rect x="7" y="8" width="10" height="13" rx="2"/><path d="M12 12v5"/>',
        'cadena' => '<rect x="3" y="9" width="8" height="6" rx="3"/><rect x="13" y="9" width="8" height="6" rx="3"/><path d="M11 12h2"/>',
        'rodillo' => '<circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="2"/><path d="M12 5v2"/><path d="M12 17v2"/>',
        'pieza' => '<path d="M14 7a4 4 0 1 0-4 4l-6 6 2 2 6-6a4 4 0 0 0 4-4l-2 2-2-2 2-2z"/>',
    ];

    /** Icono por tipo de máquina (tal como se guarda en la flota). */
    public static function machine(?string $type, ?string $model = null): string
    {
        $t = mb_strtolower((string) $type);
        $key = match (true) {
            str_contains($t, 'miniexc') => 'miniexcavadora',
            str_contains($t, 'excav') => 'excavadora',
            str_contains($t, 'pala') || str_contains($t, 'cargadora') => 'pala',
            str_contains($t, 'bulldozer') => 'bulldozer',
            str_contains($t, 'dumper') => 'dumper',
            str_contains($t, 'motoniv') => 'motoniveladora',
            str_contains($t, 'compact') || str_contains($t, 'rodillo') => 'compactador',
            str_contains($t, 'tractor') => 'tractor',
            str_contains($t, 'motor') => 'motor',
            str_contains($t, 'martillo') => 'martillo',
            str_contains($t, 'equipo') => 'equipo',
            str_contains($t, 'pieza') => 'piezas',
            default => 'maquina',
        };

        return self::svg($key);
    }

    /** Icono por etiqueta de pieza ("Aire primario", "Cuchilla", "Cantonera"…). */
    public static function part(string $label, bool $filter = false): string
    {
        $l = mb_strtolower($label);
        $key = match (true) {
            $filter || str_contains($l, 'filtro') || str_contains($l, 'aire') || str_contains($l, 'aceite') || str_contains($l, 'combustible') || str_contains($l, 'hidráulico') || str_contains($l, 'refrigerante') || str_contains($l, 'transmisión') || str_contains($l, 'cabina') || str_contains($l, 'respiradero') || str_contains($l, 'prefiltro') => 'filtro',
            str_contains($l, 'sobrecant') || str_contains($l, 'cantonera') => 'cantonera',
            str_contains($l, 'cuchilla') => 'cuchilla',
            str_contains($l, 'punta') || str_contains($l, 'diente') || str_contains($l, 'puntero') => 'punta',
            str_contains($l, 'tornillo') || str_contains($l, 'tuerca') => 'tornillo',
            str_contains($l, 'pasador') => 'pasador',
            str_contains($l, 'correa') => 'correa',
            str_contains($l, 'ripper') => 'ripper',
            str_contains($l, 'protector') => 'protector',
            str_contains($l, 'grasa') => 'grasa',
            str_contains($l, 'cadena') => 'cadena',
            str_contains($l, 'rodillo') || str_contains($l, 'zapata') || str_contains($l, 'rueda') => 'rodillo',
            default => 'pieza',
        };

        return self::svg($key);
    }

    public static function svg(string $key): string
    {
        $p = self::PATHS[$key] ?? self::PATHS['pieza'];

        return '<svg class="ico ico-'.$key.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$p.'</svg>';
    }
}
