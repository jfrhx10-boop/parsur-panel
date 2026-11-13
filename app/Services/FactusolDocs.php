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
        'facturarec' => ['F_FRE', 'FRE', 'F_LFR', 'LFR'], // compras: facturas recibidas (también hacen de entrada)
        'pedidoprov' => ['F_PPR', 'PPR', 'F_LPP', 'LPP'], // compras: pedidos a proveedor (con lo pendiente de recibir por línea)
    ];

    private const CHUNK = 400; // documentos por llamada de líneas

    /** @return array{year:int,steps:array<int,string>,finished:bool} */
    public function run(int $year, int $seconds = 50): array
    {
        $api = new FactusolClient;
        $start = microtime(true);
        $steps = [];
        $key = 'fs_docs_step_'.$year;
        $step = (int) Setting::get($key, '0'); // 0..11: cabeceras y líneas de cada tipo
        $types = array_keys(self::TABLES);

        // Ya se cargó todo el ejercicio: actualización incremental (nuevos documentos y cambios de estado)
        if ($step >= count(self::TABLES) * 2) {
            return ['year' => $year, 'steps' => $this->refresh($api, $year, $seconds), 'finished' => true];
        }

        while ($step < count(self::TABLES) * 2 && microtime(true) - $start < $seconds) {
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
        $finished = $step >= count(self::TABLES) * 2;
        if ($finished) {
            Setting::put('fs_docs_last_'.$year, now()->toIso8601String());
            \Illuminate\Support\Facades\Cache::forget('repurchase:all');
        }

        return ['year' => $year, 'steps' => $steps, 'finished' => $finished];
    }

    /**
     * Actualización incremental: vuelve a leer las cabeceras del ejercicio (una consulta por tipo),
     * guarda solo lo que ha cambiado y trae las líneas de los documentos nuevos o modificados.
     *
     * @return array<int,string>
     */
    private function refresh(FactusolClient $api, int $year, int $seconds, ?array $only = null): array
    {
        $start = microtime(true);
        $steps = [];
        foreach (self::TABLES as $type => [$hdr, $hp, $lin, $lp]) {
            if ($only !== null && ! in_array($type, $only, true)) {
                continue;
            }
            if (microtime(true) - $start > $seconds) {
                $steps[] = 'tiempo agotado; pulsa otra vez para seguir';
                break;
            }
            $entrada = in_array($type, ['entrada', 'facturarec', 'pedidoprov'], true);
            $ext = $type === 'facturarec' ? 'FAC' : ($type === 'entrada' ? 'ALB' : null);
            $cols = $entrada
                ? "COD$hp,REF$hp,FEC$hp,PRO$hp,PNO$hp,EST$hp,BAS1$hp,TOT$hp,OB1$hp".($ext ? ",{$ext}$hp" : '')
                : "COD$hp,REF$hp,FEC$hp,CLI$hp,CNO$hp,EST$hp,BAS1$hp,TOT$hp,OB1$hp";
            if ($type === 'albaran') {
                $cols .= ',COBALB';
            }
            $rows = $api->query($year, "SELECT $cols FROM $hdr");
            $existing = FsDoc::where('type', $type)->where('year', $year)->get(['id', 'code', 'status', 'total', 'base', 'ref', 'date', 'lines_count', 'paid'])->keyBy('code');
            $changed = [];
            $new = 0;
            $upd = 0;
            foreach ($rows as $r) {
                $code = (int) ($r["COD$hp"] ?? 0);
                if ($code <= 0) {
                    continue;
                }
                $date = substr((string) ($r["FEC$hp"] ?? ''), 0, 10);
                $date = preg_match('/^\d{4}-\d\d-\d\d$/', $date) && ! str_starts_with($date, '1900') ? $date : null;
                $attrs = [
                    'ref' => self::clean($r["REF$hp"] ?? null), 'date' => $date,
                    'codcli' => $entrada ? null : ((int) ($r["CLI$hp"] ?? 0) ?: null),
                    'codpro' => $entrada ? ((int) ($r["PRO$hp"] ?? 0) ?: null) : null,
                    'customer_name' => self::clean($r[$entrada ? "PNO$hp" : "CNO$hp"] ?? null),
                    'status' => (int) ($r["EST$hp"] ?? 0), 'base' => (float) ($r["BAS1$hp"] ?? 0), 'total' => (float) ($r["TOT$hp"] ?? 0),
                    'notes' => self::clean($r["OB1$hp"] ?? null), 'ext_ref' => $entrada && $ext ? self::clean($r["{$ext}$hp"] ?? null) : null,
                ];
                if ($type === 'albaran') {
                    $attrs['paid'] = (int) ($r['COBALB'] ?? 0);
                }
                $e = $existing[$code] ?? null;
                if (! $e) {
                    FsDoc::create(['type' => $type, 'year' => $year, 'code' => $code, 'synced_at' => now()] + $attrs);
                    $changed[] = $code;
                    $new++;
                } elseif ((int) $e->status !== $attrs['status'] || abs((float) $e->total - $attrs['total']) > 0.004 || abs((float) $e->base - $attrs['base']) > 0.004
                    || (string) $e->ref !== (string) $attrs['ref'] || optional($e->date)->toDateString() !== $date || (int) $e->lines_count === 0
                    || (array_key_exists('paid', $attrs) && ($e->paid === null || (int) $e->paid !== $attrs['paid']))) {
                    FsDoc::where('id', $e->id)->update($attrs + ['synced_at' => now()]);
                    $changed[] = $code;
                    $upd++;
                }
            }
            // Lo que se ha borrado en FactuSOL desaparece también del panel (solo si la consulta trajo datos).
            if (count($rows) > 0) {
                $present = [];
                foreach ($rows as $r) {
                    $present[(int) ($r["COD$hp"] ?? 0)] = true;
                }
                $gone = $existing->filter(fn ($e, $code) => ! isset($present[(int) $code]));
                if ($gone->isNotEmpty() && $gone->count() <= max(50, intdiv(count($rows), 4))) {
                    $ids = $gone->pluck('id')->all();
                    FsDocLine::whereIn('doc_id', $ids)->delete();
                    FsDoc::whereIn('id', $ids)->delete();
                    if ($type === 'albaran') {
                        \App\Models\PanelAlbaran::where('year', $year)->whereIn('albaran_code', $gone->keys()->all())->whereNull('removed_at')->update(['removed_at' => now()]);
                    }
                    if ($type === 'presupuesto') {
                        \App\Models\Quote::where('fs_year', $year)->whereIn('fs_code', $gone->keys()->all())->where('kind', 'presupuesto')->get()->each(function ($q) {
                            $q->lines()->delete();
                            $q->delete();
                        });
                    }
                    $steps[] = "$type: {$gone->count()} borrados en FactuSOL";
                }
            }
            if ($type === 'pedido') {
                // Los pedidos en curso se releen siempre: la cantidad pendiente por línea cambia sin que cambie la cabecera.
                $open = FsDoc::where('type', 'pedido')->where('year', $year)->whereIn('status', [0, 1])->pluck('code')->all();
                $changed = array_values(array_unique(array_merge($changed, $open)));
            }
            if ($type === 'pedidoprov') {
                $open = FsDoc::where('type', 'pedidoprov')->where('year', $year)->whereIn('status', [0, 1])->pluck('code')->all();
                $changed = array_values(array_unique(array_merge($changed, $open)));
            }
            $n = 0;
            foreach (array_chunk($changed, 150) as $chunk) {
                $n += $this->syncLinesIn($api, $year, $type, $lin, $lp, $chunk);
            }
            $steps[] = "$type: $new nuevos, $upd actualizados".($n ? " ($n líneas)" : '');
        }
        Setting::put('fs_docs_last_'.$year, now()->toIso8601String());
        \Illuminate\Support\Facades\Cache::forget('repurchase:all');

        return $steps;
    }

    /**
     * Actualización rápida al abrir un listado: relee solo los tipos pedidos (máx. una vez por minuto).
     * Si FactuSOL falla, el panel sigue con lo que tiene.
     *
     * @param  array<int,string>  $types
     */
    public function quick(array $types, int $seconds = 20, bool $force = false): void
    {
        $year = (int) date('Y');
        if (Setting::get('fs_docs_step_'.$year, '0') < count(self::TABLES) * 2) {
            return; // la carga inicial aún no ha terminado
        }
        $key = 'fs:quick:'.implode(',', $types);
        if (! $force && \Illuminate\Support\Facades\Cache::has($key)) {
            return;
        }
        \Illuminate\Support\Facades\Cache::put($key, 1, 60);
        try {
            $this->refresh(new FactusolClient, $year, $seconds, $types);
        } catch (\Throwable $e) {
            // sin conexión: se muestra lo ya sincronizado
        }
    }

    public static function reset(int $year): void
    {
        Setting::put('fs_docs_step_'.$year, '0');
        Setting::put('fs_docs_step_'.$year.'_lines_from', '0');
    }

    private function syncHeaders(FactusolClient $api, int $year, string $type, string $table, string $p): int
    {
        $entrada = in_array($type, ['entrada', 'facturarec', 'pedidoprov'], true);
        $ext = $type === 'facturarec' ? 'FAC' : ($type === 'entrada' ? 'ALB' : null);
        $cols = $entrada
            ? "TIP$p,COD$p,REF$p,FEC$p,PRO$p,PNO$p,EST$p,BAS1$p,TOT$p,OB1$p".($ext ? ",{$ext}$p" : '')
            : "TIP$p,COD$p,REF$p,FEC$p,CLI$p,CNO$p,EST$p,BAS1$p,TOT$p,OB1$p".($type === 'albaran' ? ",PED$p" : '');
        $rows = $api->query($year, "SELECT $cols FROM $table");
        $n = 0;
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::transaction(function () use ($chunk, $year, $type, $p, $entrada, $ext, &$n) {
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
                        'ext_ref' => $entrada && $ext ? self::clean($r["{$ext}$p"] ?? null) : null,
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
        $pen = in_array($type, ['pedido', 'pedidoprov'], true) ? ",PEN$p" : '';
        $rows = $api->query($year, "SELECT COD$p,POS$p,ART$p,DES$p,CAN$p,PRE$p,DT1$p,TOT$p$pen FROM $table WHERE COD$p BETWEEN $from AND $to");
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
                        'pending' => isset($r["PEN$p"]) ? (float) $r["PEN$p"] : null,
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

    /** Líneas solo de los documentos indicados (lista de códigos). */
    private function syncLinesIn(FactusolClient $api, int $year, string $type, string $table, string $p, array $codes): int
    {
        $codes = array_values(array_unique(array_map('intval', $codes)));
        if (! $codes) {
            return 0;
        }
        $in = implode(',', $codes);
        $pen = in_array($type, ['pedido', 'pedidoprov'], true) ? ",PEN$p" : '';
        $rows = $api->query($year, "SELECT COD$p,POS$p,ART$p,DES$p,CAN$p,PRE$p,DT1$p,TOT$p$pen FROM $table WHERE COD$p IN ($in)");
        $byCode = [];
        foreach ($rows as $r) {
            $byCode[(int) $r["COD$p"]][] = $r;
        }
        $docs = FsDoc::where('type', $type)->where('year', $year)->whereIn('code', $codes)->get()->keyBy('code');
        $n = 0;
        DB::transaction(function () use ($byCode, $docs, $p, &$n) {
            foreach ($docs as $code => $doc) {
                FsDocLine::where('doc_id', $doc->id)->delete();
                $ins = [];
                foreach ($byCode[$code] ?? [] as $r) {
                    $ins[] = [
                        'doc_id' => $doc->id, 'pos' => (int) ($r["POS$p"] ?? 0), 'art' => self::clean($r["ART$p"] ?? null),
                        'description' => mb_substr((string) self::clean($r["DES$p"] ?? ''), 0, 255) ?: null,
                        'qty' => (float) ($r["CAN$p"] ?? 0), 'pending' => isset($r["PEN$p"]) ? (float) $r["PEN$p"] : null, 'price' => (float) ($r["PRE$p"] ?? 0), 'discount' => (float) ($r["DT1$p"] ?? 0), 'total' => (float) ($r["TOT$p"] ?? 0),
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
