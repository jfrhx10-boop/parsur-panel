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
        // ---- menú ----
        'home' => '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h5v-6h4v6h5V10"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><circle cx="17" cy="9" r="2.5"/><path d="M15.5 14.5a5 5 0 0 1 6 5"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3c3 3 3 15 0 18"/><path d="M12 3c-3 3-3 15 0 18"/>',
        'refresh' => '<path d="M20 12a8 8 0 1 1-2.3-5.7"/><path d="M20 4v5h-5"/>',
        'check-list' => '<path d="M4 7l2 2 3-3"/><path d="M4 13l2 2 3-3"/><path d="M4 19l2 2 3-3"/><path d="M12 7h9"/><path d="M12 13h9"/><path d="M12 19h9"/>',
        'box' => '<path d="M3 8l9-4 9 4v9l-9 4-9-4z"/><path d="M3 8l9 4 9-4"/><path d="M12 12v9"/>',
        'truck' => '<path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'tag' => '<path d="M3 12V4h8l9 9-8 8z"/><circle cx="7.5" cy="8.5" r="1.5"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'gear' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'chart' => '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/>',
        'filter' => '<path d="M3 5h18l-7 8v6l-4 2v-8z"/>',
        'wallet' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M3 10h18"/><path d="M16 15h2"/><path d="M7 7V5a2 2 0 0 1 2-2h8"/>',
        // ---- contacto ----
        'phone' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
        'mobile' => '<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'pin' => '<path d="M12 21s-7-6.5-7-11a7 7 0 0 1 14 0c0 4.5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/>',
        'whatsapp' => '<path d="M4 20l1.3-3.8A8 8 0 1 1 8 19.2z"/><path d="M9.5 9.5c0 3 2.5 5.5 5.5 5.5l1-1.5-2-1-1 1a4 4 0 0 1-2-2l1-1-1-2z"/>',
        'building' => '<path d="M4 21V5l8-3v19"/><path d="M12 9l8 2v10"/><path d="M7 8h2"/><path d="M7 12h2"/><path d="M7 16h2"/><path d="M15 14h2"/><path d="M15 18h2"/>',
        // ---- documentos ----
        'doc-pedido' => '<path d="M8 4h8l1 2h2v15H5V6h2z"/><path d="M9 3h6v3H9z"/><path d="M8 11h8"/><path d="M8 15h5"/>',
        'doc-albaran' => '<path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'doc-factura' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6"/><path d="M9 12h6"/><path d="M9 16h3"/>',
        'doc-entrada' => '<path d="M3 9l9-5 9 5v10l-9 5-9-5z"/><path d="M3 9l9 5 9-5"/><path d="M12 14v10"/><path d="M12 2v5"/><path d="M9 5l3 2 3-2"/>',
        'doc-presupuesto' => '<path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4"/><path d="M9 12h6"/><path d="M9 16h6"/><path d="M12 10v8"/>',
        'doc-cobro' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M6 12h.01"/><path d="M18 12h.01"/>',
        'cliente' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
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

    /** Colores de rótulo por marca (fondo, texto). */
    private const BRAND_COLORS = [
        'cat' => ['#FFCD11', '#111'], 'caterpillar' => ['#FFCD11', '#111'], 'volvo' => ['#1B365D', '#fff'], 'jcb' => ['#F9B517', '#111'],
        'komatsu' => ['#1F3A93', '#fff'], 'hitachi' => ['#F26A21', '#fff'], 'kubota' => ['#E8601C', '#fff'], 'case' => ['#C8102E', '#fff'],
        'new-holland' => ['#F2B807', '#111'], 'liebherr' => ['#FFD500', '#111'], 'bobcat' => ['#F26522', '#fff'], 'hyundai' => ['#0A2D5F', '#fff'],
        'doosan' => ['#1C3F94', '#fff'], 'manitou' => ['#D71920', '#fff'], 'takeuchi' => ['#C1121F', '#fff'], 'yanmar' => ['#C8102E', '#fff'],
        'terex' => ['#D52B1E', '#fff'], 'deutz' => ['#00843D', '#fff'], 'scania' => ['#0F2C57', '#fff'], 'iveco' => ['#1D4F91', '#fff'],
        'perkins' => ['#0057A8', '#fff'], 'cummins' => ['#C8102E', '#fff'], 'hidromek' => ['#D7262E', '#fff'], 'fiat' => ['#8E0C1F', '#fff'],
        'mecalac' => ['#1A6B3A', '#fff'], 'wacker' => ['#F2C200', '#111'], 'neuson' => ['#F2C200', '#111'], 'sany' => ['#D4121A', '#fff'],
        'bomag' => ['#F2B807', '#111'], 'dynapac' => ['#E31E26', '#fff'], 'hamm' => ['#1B3F8B', '#fff'], 'merlo' => ['#1F8A3B', '#fff'],
        'john-deere' => ['#367C2B', '#FFDE00'], 'massey-ferguson' => ['#C8102E', '#fff'], 'atlas' => ['#E2001A', '#fff'], 'kobelco' => ['#1C4E9D', '#fff'],
    ];

    /** Rótulo de marca: logo subido si lo hay; si no, nombre sobre su color. */
    public static function brand(?string $brand, string $size = 'md'): string
    {
        $brand = trim((string) $brand);
        if ($brand === '') {
            return '';
        }
        $slug = \App\Models\BrandLogo::slugFor($brand);
        if (isset(\App\Models\BrandLogo::available()[$slug])) {
            return '<img class="bl bl-'.$size.'" src="/marcas/'.$slug.'" alt="'.e($brand).'" title="'.e($brand).'">';
        }
        [$bg, $fg] = self::BRAND_COLORS[$slug] ?? ['#444', '#fff'];

        return '<span class="bl bl-'.$size.' bl-txt" style="background:'.$bg.';color:'.$fg.'">'.e(mb_strtoupper($brand)).'</span>';
    }

    public static function svg(string $key): string
    {
        $p = self::PATHS[$key] ?? self::PATHS['pieza'];

        return '<svg class="ico ico-'.$key.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$p.'</svg>';
    }
}
