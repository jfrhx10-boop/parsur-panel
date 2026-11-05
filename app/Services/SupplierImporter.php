<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierConsumption;
use App\Models\SupplierReference;
use Illuminate\Support\Facades\DB;

/** Importa el histórico de compras de un proveedor desde un JSON (convertido de su Excel). Se puede repetir sin duplicar. */
class SupplierImporter
{
    public function importJson(string $path): array
    {
        $json = json_decode((string) file_get_contents($path), true);
        if (!is_array($json) || empty($json['supplier']['code']) || !isset($json['refs'])) {
            throw new \RuntimeException('Archivo de proveedor no válido.');
        }
        $s = $json['supplier'];
        $nRefs = 0;
        $nRows = 0;

        DB::transaction(function () use ($s, $json, &$nRefs, &$nRows) {
            $supplier = Supplier::updateOrCreate(['code' => $s['code']], [
                'name' => $s['name'] ?? $s['code'],
                'account' => $s['account'] ?? null,
                'ytd_prev' => $s['ytd_prev'] ?? null,
                'source' => $s['source'] ?? null,
                'imported_at' => now(),
            ]);
            foreach ($json['refs'] as $r) {
                $ref = SupplierReference::updateOrCreate(
                    ['supplier_id' => $supplier->id, 'ref' => $r['ref']],
                    ['family' => $r['family'] ?? null]
                );
                $nRefs++;
                foreach ($r['data'] as $year => $v) {
                    SupplierConsumption::updateOrCreate(
                        ['supplier_reference_id' => $ref->id, 'year' => (int) $year],
                        ['quantity' => (int) $v[0], 'amount' => (float) $v[1]]
                    );
                    $nRows++;
                }
            }
        });

        return ['refs' => $nRefs, 'rows' => $nRows];
    }
}
