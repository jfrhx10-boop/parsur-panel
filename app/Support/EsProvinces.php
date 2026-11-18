<?php

namespace App\Support;

/** Códigos de provincia de WooCommerce (ES) → nombre. */
class EsProvinces
{
    private const MAP = [
        'C' => 'A Coruña', 'VI' => 'Álava', 'AB' => 'Albacete', 'A' => 'Alicante', 'AL' => 'Almería', 'O' => 'Asturias', 'AV' => 'Ávila',
        'BA' => 'Badajoz', 'PM' => 'Baleares', 'B' => 'Barcelona', 'BU' => 'Burgos', 'CC' => 'Cáceres', 'CA' => 'Cádiz', 'S' => 'Cantabria',
        'CS' => 'Castellón', 'CE' => 'Ceuta', 'CR' => 'Ciudad Real', 'CO' => 'Córdoba', 'CU' => 'Cuenca', 'GI' => 'Girona', 'GR' => 'Granada',
        'GU' => 'Guadalajara', 'SS' => 'Gipuzkoa', 'H' => 'Huelva', 'HU' => 'Huesca', 'J' => 'Jaén', 'LO' => 'La Rioja', 'GC' => 'Las Palmas',
        'LE' => 'León', 'L' => 'Lleida', 'LU' => 'Lugo', 'M' => 'Madrid', 'MA' => 'Málaga', 'ML' => 'Melilla', 'MU' => 'Murcia', 'NA' => 'Navarra',
        'OR' => 'Ourense', 'P' => 'Palencia', 'PO' => 'Pontevedra', 'SA' => 'Salamanca', 'TF' => 'Santa Cruz de Tenerife', 'SG' => 'Segovia',
        'SE' => 'Sevilla', 'SO' => 'Soria', 'T' => 'Tarragona', 'TE' => 'Teruel', 'TO' => 'Toledo', 'V' => 'Valencia', 'VA' => 'Valladolid',
        'BI' => 'Bizkaia', 'ZA' => 'Zamora', 'Z' => 'Zaragoza',
    ];

    public static function name(string $code): ?string
    {
        return self::MAP[strtoupper(trim($code))] ?? null;
    }
}
