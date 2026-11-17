<?php

use App\Services\Activity;
use App\Services\FactusolClient;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Una vez (pedido por Juan): las facturas recibidas 10164, 57628 y 57629 se numeraron fuera de la serie
 * (tecleadas a mano en FactuSOL detrás de la 806). Pasan a 807, 808 y 809 con los mismos datos.
 * No tienen pagos; las entradas de las que vienen no guardan el nº de factura. Por API no se mueve el stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        $api = new FactusolClient;
        $y = 2026;
        foreach ([10164 => 807, 57628 => 808, 57629 => 809] as $old => $new) {
            try {
                $h = $api->table($y, 'F_FRE', "CODFRE = $old")[0] ?? null;
                $lines = $api->table($y, 'F_LFR', "CODLFR = $old");
                if (! $h || $api->table($y, 'F_FRE', "CODFRE = $new") || $api->table($y, 'F_LFR', "CODLFR = $new")) {
                    Activity::log('renumerar_fre', "$old → $new: no hecho (no existe el origen o el destino está ocupado)");

                    continue;
                }
                if ((int) ($h['ESTFRE'] ?? 0) !== 0) {
                    Activity::log('renumerar_fre', "$old → $new: no hecho (tiene pagos)");

                    continue;
                }
                $h['CODFRE'] = $new;
                $api->insert($y, 'F_FRE', $h);
                foreach ($lines as $l) {
                    $l['CODLFR'] = $new;
                    $api->insert($y, 'F_LFR', $l);
                }
                $okH = count($api->table($y, 'F_FRE', "CODFRE = $new")) === 1;
                $okL = count($api->table($y, 'F_LFR', "CODLFR = $new")) === count($lines);
                if (! $okH || ! $okL) {
                    Activity::log('renumerar_fre', "$old → $new: la copia no cuadra; el original se deja como estaba");

                    continue;
                }
                $api->delete($y, 'F_LFR', "CODLFR = $old");
                $api->delete($y, 'F_FRE', "CODFRE = $old");
                DB::table('panel_entradas')->where('year', $y)->where('entry_code', $old)->where('kind', 'facturarec')->update(['entry_code' => $new]);
                DB::table('supplier_notes')->where('entry_year', $y)->where('entry_code', $old)->update(['entry_code' => $new]);
                $ids = DB::table('fs_docs')->where('type', 'facturarec')->where('year', $y)->where('code', $old)->pluck('id');
                DB::table('fs_doc_lines')->whereIn('doc_id', $ids)->delete();
                DB::table('fs_docs')->whereIn('id', $ids)->delete();
                Activity::log('renumerar_fre', "Factura recibida $old → $new hecha (".count($lines).' líneas)');
            } catch (\Throwable $e) {
                Activity::log('renumerar_fre', "$old → $new ERROR ".mb_substr($e->getMessage(), 0, 150));
            }
        }
    }

    public function down(): void {}
};
