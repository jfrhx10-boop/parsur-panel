<?php

namespace App\Services;

use App\Models\Article;
use App\Models\FsSupplier;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/** Artículos (con stock y tarifa 1) y proveedores de FactuSOL. */
class FactusolCatalog
{
    /** @return array{articles:int,suppliers:int} */
    public function run(): array
    {
        $api = new FactusolClient;
        $year = (int) date('Y');

        $fam = [];
        foreach ($api->query($year, 'SELECT CODFAM, DESFAM FROM F_FAM') as $r) {
            $fam[(string) $r['CODFAM']] = trim((string) $r['DESFAM']);
        }
        $sto = [];
        foreach ($api->query($year, 'SELECT ARTSTO, ACTSTO, DISSTO, MINSTO, MAXSTO FROM F_STO') as $r) {
            $sto[(string) $r['ARTSTO']] = $r;
        }
        $tar = [];
        foreach ($api->query($year, 'SELECT ARTLTA, MARLTA, PRELTA FROM F_LTA WHERE TARLTA = 1') as $r) {
            $tar[(string) $r['ARTLTA']] = $r;
        }
        $arts = $api->query($year, 'SELECT CODART, DESART, FAMART, PCOART, EANART, EQUART, UBIART, FALART, OBSART FROM F_ART');

        $n = 0;
        foreach (array_chunk($arts, 300) as $chunk) {
            DB::transaction(function () use ($chunk, $fam, $sto, $tar, &$n) {
                foreach ($chunk as $r) {
                    $code = trim((string) ($r['CODART'] ?? ''));
                    if ($code === '') {
                        continue;
                    }
                    $s = $sto[$code] ?? [];
                    $t = $tar[$code] ?? [];
                    $famCode = trim((string) ($r['FAMART'] ?? ''));
                    $created = substr((string) ($r['FALART'] ?? ''), 0, 10);
                    Article::updateOrCreate(['codart' => $code], [
                        'description' => mb_substr(trim((string) $r['DESART']), 0, 255) ?: null,
                        'family_code' => $famCode ?: null,
                        'family' => $fam[$famCode] ?? null,
                        'cost' => (float) ($r['PCOART'] ?? 0),
                        'price' => (float) ($t['PRELTA'] ?? 0),
                        'margin' => isset($t['MARLTA']) ? (float) $t['MARLTA'] : null,
                        'ean' => trim((string) $r['EANART']) ?: null,
                        'equivalent' => mb_substr(trim((string) $r['EQUART']), 0, 120) ?: null,
                        'location' => trim((string) $r['UBIART']) ?: null,
                        'stock' => (float) ($s['ACTSTO'] ?? 0),
                        'stock_available' => (float) ($s['DISSTO'] ?? 0),
                        'stock_min' => (float) ($s['MINSTO'] ?? 0),
                        'stock_max' => (float) ($s['MAXSTO'] ?? 0),
                        'factusol_created_at' => preg_match('/^\d{4}-\d\d-\d\d$/', $created) && ! str_starts_with($created, '1900') ? $created : null,
                        'notes' => trim((string) $r['OBSART']) ?: null,
                        'synced_at' => now(),
                    ]);
                    $n++;
                }
            });
        }

        $m = 0;
        foreach ($api->query($year, 'SELECT CODPRO, NOFPRO, NOCPRO, NIFPRO, TELPRO, EMAPRO, WEBPRO, DOMPRO, POBPRO, CPOPRO, PROPRO, OBSPRO FROM F_PRO') as $r) {
            $code = (int) ($r['CODPRO'] ?? 0);
            if ($code <= 0) {
                continue;
            }
            $c = fn ($k) => ($v = trim((string) ($r[$k] ?? ''))) === '' ? null : $v;
            FsSupplier::updateOrCreate(['codpro' => $code], [
                'name' => $c('NOFPRO') ?? $c('NOCPRO') ?? 'Proveedor '.$code,
                'trade_name' => $c('NOCPRO'), 'nif' => $c('NIFPRO'), 'phone' => $c('TELPRO'), 'email' => $c('EMAPRO'), 'web' => $c('WEBPRO'),
                'address' => $c('DOMPRO'), 'city' => $c('POBPRO'), 'postcode' => $c('CPOPRO'), 'province' => $c('PROPRO'), 'notes' => $c('OBSPRO'),
                'synced_at' => now(),
            ]);
            $m++;
        }
        Setting::put('fs_catalog_last', now()->toIso8601String());

        return ['articles' => $n, 'suppliers' => $m];
    }
}
