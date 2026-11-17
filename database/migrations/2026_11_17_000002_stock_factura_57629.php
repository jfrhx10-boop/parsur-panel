<?php

use App\Services\FactusolClient;
use Illuminate\Database\Migrations\Migration;

/** Una vez: la factura recibida 57629 (Cater Kom, 22M5425692 x1) se creó desde el panel sin sumar el stock. */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $api = new FactusolClient;
            $row = $api->table(2026, 'F_STO', "ARTSTO = '22M5425692'")[0] ?? null;
            if ($row && count($api->table(2026, 'F_LFR', 'CODLFR = 57629')) === 1) {
                $row['ACTSTO'] = (float) $row['ACTSTO'] + 1;
                $row['DISSTO'] = (float) $row['DISSTO'] + 1;
                $api->update(2026, 'F_STO', $row);
                \App\Services\Activity::log('stock_movido', '22M5425692 +1 (factura recibida 57629, corrección)');
            }
        } catch (\Throwable $e) {
            \App\Services\Activity::log('stock_error', '57629: '.mb_substr($e->getMessage(), 0, 150));
        }
    }

    public function down(): void {}
};
