<?php

namespace App\Services;

use App\Models\FsDoc;
use App\Models\FsDocLine;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Sincroniza los documentos de venta de FactuSOL (pedidos, albaranes, facturas) con sus líneas.
 * Trabaja por ejercicio y por pasos, con un presupuesto de tiempo, para poder repetir el botón.
 */
class FactusolDocs
{
    private const TABLES = [
        // tipo => [cabecera, prefijo cabecera, líneas, prefijo líneas]
        'pedido' => ['F_PCL', 'PCL', 'F_LPC', 'LPC'],
        'albaran' => ['F_ALB', 'ALB', 'F_LAL', 'LAL'],
        'factura' => ['F_FAC', 'FAC', 'F_LFA', 'LFA'],
        'entrada' => ['F_ENT', 'ENT', 'F_LEN', 'LEN'], // compras: entradas de proveedor
        'presupuesto' => ['F_PRE', 'PRE', 'F_LPS', 'LPS'],
    ];

    private const CHUNK = 400; // documentos por llamada de líneas

    /** @return array{year:int,steps:array<int,string>,finished:bool} */
    public function run(int $year, int $seconds = 50): array
    {
        $api = new FactusolClient;
        $start = microtime(true);
        $steps = [];
        $key = 'fs_docs_step_'.$year;
        $step = (int) Setting::get($key, '0'); // 0..5: cabeceras y líneas de cada tipo
        $types = array_keys(self::TABLES);

        while ($step < 10 && microtime(true) - $start < $seconds) {
            $type = $types[intdiv($step, 2)];
            [$hdr, $hp, $lin, $lp] = self::TABLES[$type];
            if ($step % 2 === 0) {
                $n = $this->syncHeaders($api, $year, $type, $hdr, $hp);
                $steps[] = "$type: $n documentos";
                $step++;
                Setting::put($key, (string) $step);
                Setting::put($key.'_lines_from', '0');
            } else {
                $from = (int) Setting::get($key.'_lines_from', '0');
                $codes = FsDoc::where('type', $type)->where('year', $year)->where('code', '>', $from)->orderBy('code')->limit(self::CHUNK)->pluck('code');
                if ($codes->isEmpty()) {
                    $step++;
                    Setting::put($key, (string) $step);

                    continue;
                }
                $n = $this->syncLines($api, $year, $type, $lin, $lp, (int) $codes->first(), (int) $codes->last());
                $steps[] = "$type: líneas de {$codes->count()} documentos ($n líneas)";
                Setting::put($key.'_lines_from', (string) $codes->last());
            }
        }
        $finished = $step >= 10;
        if ($finished) {
            Setting::put('fs_docs_last_'.$year, now()->toIso8601String());
        }

        return ['year' => $year, 'steps' => $steps, 'finished' => $finished];
    }

    public static function reset(int $year): void
    {
        Setting::put('fs_docs_step_'.$year, '0');
        Setting::put('fs_docs_step_'.$year.'_lines_from', '0');
    }

    private function syncHeaders(FactusolClient $api, int $year, string $type, string $table, string $p): int
    {
        $entrada = $type === 'entrada';
        $cols = $entrada
            ? "TIP$p,COD$p,REF$p,FEC$p,PRO$p,PNO$p,EST$p,BAS1$p,TOT$p,OB1$p,ALB$p"
            : "TIP$p,COD$p,REF$p,FEC$p,CLI$p,CNO$p,EST$p,BAS1$p,TOT$p,OB1$p".($type === 'albaran' ? ",PED$p" : '');
        $rows = $api->query($year, "SELECT $cols FROM $table");
        $n = 0;
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::transaction(function () use ($chunk, $year, $type, $p, $entrada, &$n) {
                foreach ($chunk as $r) {
                    $code = (int) ($r["COD$p"] ?? 0);
                    if ($code <= 0) {
                        continue;
                    }
                    $date = substr((string) ($r["FEC$p"] ?? ''), 0, 10);
                    FsDoc::updateOrCreate(['type' => $type, 'year' => $year, 'code' => $code], [
                        'ref' => self::clean($r["REF$p"] ?? null),
                        'date' => preg_match('/^\d{4}-\d\d-\d\d$/', $date) && ! str_starts_with($date, '1900') ? $date : null,
                        'codcli' => $entrada ? null : ((int) ($r["CLI$p"] ?? 0) ?: null),
                        'codpro' => $entrada ? ((int) ($r["PRO$p"] ?? 0) ?: null) : null,
                        'customer_name' => self::clean($r[$entrada ? "PNO$p" : "CNO$p"] ?? null),
                        'ext_ref' => $entrada ? self::clean($r["ALB$p"] ?? null) : null,
                        'status' => (int) ($r["EST$p"] ?? 0),
                        'base' => (float) ($r["BAS1$p"] ?? 0),
                        'total' => (float) ($r["TOT$p"] ?? 0),
                        'notes' => self::clean($r["OB1$p"] ?? null),
                        'order_code' => $type === 'albaran' ? ((int) ($r["PED$p"] ?? 0) ?: null) : null,
                        'synced_at' => now(),
                    ]);
                    $n++;
                }
            });
        }

        return $n;
    }

    private function syncLines(FactusolClient $api, int $year, string $type, string $table, string $p, int $from, int $to): int
    {
        $rows = $api->query($year, "SELECT COD$p,POS$p,ART$p,DES$p,CAN$p,PRE$p,DT1$p,TOT$p FROM $table WHERE COD$p BETWEEN $from AND $to");
        $byCode = [];
        foreach ($rows as $r) {
            $byCode[(int) $r["COD$p"]][] = $r;
        }
        $docs = FsDoc::where('type', $type)->where('year', $year)->whereBetween('code', [$from, $to])->get()->keyBy('code');
        $n = 0;
        DB::transaction(function () use ($byCode, $docs, $p, &$n) {
            foreach ($docs as $code => $doc) {
                $lines = $byCode[$code] ?? [];
                FsDocLine::where('doc_id', $doc->id)->delete();
                $ins = [];
                foreach ($lines as $r) {
                    $ins[] = [
                        'doc_id' => $doc->id,
                        'pos' => (int) ($r["POS$p"] ?? 0),
                        'art' => self::clean($r["ART$p"] ?? null),
                        'description' => mb_substr((string) self::clean($r["DES$p"] ?? ''), 0, 255) ?: null,
                        'qty' => (float) ($r["CAN$p"] ?? 0),
                        'price' => (float) ($r["PRE$p"] ?? 0),
                        'discount' => (float) ($r["DT1$p"] ?? 0),
                        'total' => (float) ($r["TOT$p"] ?? 0),
                    ];
                }
                if ($ins) {
                    FsDocLine::insert($ins);
                }
                $doc->forceFill(['lines_count' => count($ins)])->save();
                $n += count($ins);
            }
        });

        return $n;
    }

    private static function clean(mixed $v): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }
}
