<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAlert;
use App\Models\CustomerNote;

/** Convierte las observaciones de FactuSOL que no son máquinas ni piezas en avisos destacados. */
class CustomerAlerts
{
    public static function rebuild(): int
    {
        CustomerAlert::where('source', 'factusol')->delete();
        // Las notas "Observaciones de FactuSOL (sin máquina ni pieza)" de la importación antigua pasan a avisos
        CustomerNote::where('body', 'like', FleetImporter::OBS_MARK.'%')->delete();
        $n = 0;
        Customer::whereNotNull('factusol_notes')->where('factusol_notes', '!=', '')->orderBy('id')->chunkById(200, function ($chunk) use (&$n) {
            foreach ($chunk as $c) {
                $r = FleetParser::parse($c->factusol_notes);
                foreach ($r['obs'] as $line) {
                    $line = trim($line);
                    if (mb_strlen($line) < 3) {
                        continue;
                    }
                    CustomerAlert::create(['customer_id' => $c->id, 'kind' => CustomerAlert::classify($line), 'text' => mb_substr($line, 0, 500), 'source' => 'factusol', 'active' => true]);
                    $n++;
                }
            }
        });

        return $n;
    }
}
