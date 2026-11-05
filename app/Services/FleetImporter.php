<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\Machine;
use Illuminate\Support\Facades\DB;

/** Pasa las observaciones de FactuSOL a flota (máquinas + piezas/filtros) y notas del panel. */
class FleetImporter
{
    public const OBS_MARK = 'Observaciones de FactuSOL (sin máquina ni pieza)';

    /**
     * @return array{customers:int,machines:int,review:int,loose:int,obs:int,skipped:int,sample:array<int,string>}
     */
    public static function run(bool $apply, bool $force = false): array
    {
        $st = ['customers' => 0, 'machines' => 0, 'review' => 0, 'loose' => 0, 'obs' => 0, 'skipped' => 0, 'sample' => []];

        Customer::query()->whereNotNull('factusol_notes')->where('factusol_notes', '!=', '')->orderBy('id')->chunkById(100, function ($chunk) use (&$st, $apply, $force) {
            foreach ($chunk as $c) {
                if ($c->fleet_imported_at && !$force) {
                    $st['skipped']++;

                    continue;
                }
                $r = FleetParser::parse($c->factusol_notes);
                if (!$r['machines'] && !$r['loose'] && !$r['obs']) {
                    continue;
                }
                $st['customers']++;
                $st['machines'] += count($r['machines']);
                $st['review'] += count(array_filter($r['machines'], fn ($m) => $m['review']));
                $st['loose'] += count($r['loose']);
                $st['obs'] += count($r['obs']);
                if (count($st['sample']) < 30 && $r['machines']) {
                    $m = $r['machines'][0];
                    $st['sample'][] = $c->displayName().' → '.trim(($m['brand'] ?? '').' '.$m['model']).($m['serial'] ? ' · serie '.$m['serial'] : '').' (+'.(count($r['machines']) - 1).' más)';
                }
                if ($apply) {
                    self::apply($c, $r, $force);
                }
            }
        });

        return $st;
    }

    private static function apply(Customer $c, array $r, bool $force): void
    {
        DB::transaction(function () use ($c, $r, $force) {
            if ($force) {
                Machine::where('customer_id', $c->id)->where('source', 'import')->delete();
                CustomerNote::where('customer_id', $c->id)->where('body', 'like', self::OBS_MARK.'%')->delete();
            }
            $have = Machine::where('customer_id', $c->id)->pluck('serial')->filter()->map(fn ($s) => strtoupper($s))->all();
            foreach ($r['machines'] as $m) {
                if ($m['serial'] && in_array($m['serial'], $have, true)) {
                    continue; // ya está en la flota (p. ej. añadida a mano)
                }
                Machine::create([
                    'customer_id' => $c->id, 'type' => $m['type'], 'brand' => $m['brand'] ?: null, 'model' => $m['model'],
                    'year' => $m['year'], 'serial' => $m['serial'], 'parts' => $m['parts'] ?: null,
                    'source' => 'import', 'review' => $m['review'],
                ]);
            }
            if ($r['loose']) {
                Machine::create([
                    'customer_id' => $c->id, 'type' => 'Piezas sueltas', 'model' => '(sin máquina identificada)',
                    'parts' => implode(' · ', $r['loose']), 'source' => 'import', 'review' => true,
                ]);
            }
            if ($r['obs']) {
                \App\Models\CustomerAlert::where('customer_id', $c->id)->where('source', 'factusol')->delete();
                foreach ($r['obs'] as $line) {
                    if (mb_strlen(trim($line)) >= 3) {
                        \App\Models\CustomerAlert::create(['customer_id' => $c->id, 'kind' => \App\Models\CustomerAlert::classify($line), 'text' => mb_substr(trim($line), 0, 500), 'source' => 'factusol']);
                    }
                }
            }
            $c->forceFill(['fleet_imported_at' => now()])->save();
        });
    }
}
