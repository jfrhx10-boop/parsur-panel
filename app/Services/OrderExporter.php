<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Quote;
use RuntimeException;

/** Pasa un presupuesto del panel a pedido de cliente en FactuSOL (F_PCL + F_LPC), usando el último pedido como plantilla. */
class OrderExporter
{
    public function export(Quote $quote): array
    {
        $quote->load(['customer', 'lines']);
        if ($quote->fs_order_code) {
            throw new RuntimeException("Este presupuesto ya está en FactuSOL como pedido {$quote->fs_order_code}.");
        }
        if ($quote->lines->isEmpty()) {
            throw new RuntimeException('El presupuesto no tiene líneas.');
        }
        $codcli = (int) $quote->customer->codcli;
        if ($codcli <= 0) {
            throw new RuntimeException('El cliente no existe en FactuSOL (es un cliente solo del panel).');
        }
        $api = new FactusolClient;
        $year = (int) date('Y');
        $max = $api->query($year, 'SELECT MAX(CODPCL) AS M FROM F_PCL');
        $last = (int) ($max[0]['M'] ?? 0);
        $th = $last ? ($api->table($year, 'F_PCL', "CODPCL = $last")[0] ?? null) : null;
        $tl = $last ? ($api->table($year, 'F_LPC', "CODLPC = $last")[0] ?? null) : null;
        if (! $th || ! $tl) {
            throw new RuntimeException('No hay un pedido anterior en FactuSOL que sirva de plantilla.');
        }
        $code = $last + 1;
        $cli = $api->record($year, 'F_CLI', "CODCLI = $codcli") ?? [];
        $today = now('Europe/Madrid');

        $base = 0.0;
        $lines = [];
        $pos = 0;
        foreach ($quote->lines as $l) {
            $a = $l->art ? Article::where('codart', $l->art)->first() : null;
            $tot = round($l->qty * $l->price * (1 - $l->discount / 100), 2);
            $base += $tot;
            $row = $tl;
            foreach ($row as $k => $v) {
                if (preg_match('/^(MEM|CE1|CE2|IMA|SUM|EJE)LPC$/', $k)) {
                    $row[$k] = '';
                }
            }
            $row['CODLPC'] = $code;
            $row['POSLPC'] = ++$pos;
            $row['ARTLPC'] = (string) ($l->art ?? '');
            $row['DESLPC'] = mb_substr((string) $l->description, 0, 100);
            $row['CANLPC'] = (float) $l->qty;
            $row['PENLPC'] = (float) $l->qty;
            $row['DT1LPC'] = (float) $l->discount;
            $row['DT2LPC'] = 0;
            $row['DT3LPC'] = 0;
            $row['PRELPC'] = (float) $l->price;
            $row['TOTLPC'] = $tot;
            $row['IVALPC'] = 0;
            $row['DOCLPC'] = '';
            $row['DTPLPC'] = '';
            $row['DCOLPC'] = 0;
            $row['PIVLPC'] = 0;
            $row['TIVLPC'] = 0;
            $row['COSLPC'] = $a ? (float) $a->cost : 0;
            $lines[] = $row;
        }
        $base = round($base, 2);
        $pct = (float) ($th['PIVA1PCL'] ?? 21);
        $iva = round($base * $pct / 100, 2);

        $h = $th;
        foreach ($h as $k => $v) {
            if (preg_match('/^(NET|BAS|IIVA|IREC|IRET|IDTO|IPPA|IPOR|IFIN|PDTO|PPPA|PPOR|PFIN)\d?PCL$|^TOTPCL$/', $k) && is_numeric($v)) {
                $h[$k] = 0;
            }
        }
        foreach (['OB1', 'OB2', 'REF', 'PAS', 'TRZ', 'COM', 'TPO', 'PEN', 'PPO'] as $p) {
            if (array_key_exists($p.'PCL', $h)) {
                $h[$p.'PCL'] = '';
            }
        }
        $h['CODPCL'] = $code;
        $h['FECPCL'] = $today->format('Y-m-d').'T00:00:00';
        $h['HORPCL'] = '1900-01-01T'.$today->format('H:i:s');
        $h['FUMPCL'] = $today->format('Y-m-d').'T00:00:00';
        $h['ESTPCL'] = 0;
        $h['CLIPCL'] = $codcli;
        $h['CNOPCL'] = (string) ($cli['NOFCLI'] ?? $quote->customer->displayName());
        $h['CDOPCL'] = (string) ($cli['DOMCLI'] ?? '');
        $h['CPOPCL'] = (string) ($cli['POBCLI'] ?? '');
        $h['CCPPCL'] = (string) ($cli['CPOCLI'] ?? '');
        $h['CPRPCL'] = (string) ($cli['PROCLI'] ?? '');
        $h['CNIPCL'] = (string) ($cli['NIFCLI'] ?? '');
        $h['TELPCL'] = (string) ($cli['TELCLI'] ?? '');
        $h['CEMPCL'] = (string) ($cli['EMACLI'] ?? '');
        $h['REFPCL'] = mb_substr('Panel '.$quote->number, 0, 20);
        $h['OB1PCL'] = 'Pedido creado desde el panel ('.$quote->number.')';
        $h['NET1PCL'] = $base;
        $h['BAS1PCL'] = $base;
        $h['IIVA1PCL'] = $iva;
        $h['TOTPCL'] = round($base + $iva, 2);

        $api->insert($year, 'F_PCL', $h);
        foreach ($lines as $row) {
            $api->insert($year, 'F_LPC', $row);
        }
        $chk = $api->table($year, 'F_PCL', "CODPCL = $code")[0] ?? null;
        $nl = count($api->table($year, 'F_LPC', "CODLPC = $code"));
        if (! $chk || $nl !== count($lines)) {
            throw new RuntimeException("Pedido $code escrito, pero la comprobación no cuadra (cabecera ".($chk ? 'sí' : 'NO').", líneas $nl de ".count($lines).'). Revísalo en FactuSOL.');
        }
        $quote->update(['fs_order_code' => $code, 'status' => 'aceptado']);
        Activity::log('presupuesto_a_pedido_factusol', $quote->number.' → pedido '.$code);
        try {
            (new FactusolDocs)->run($year, 25); // trae el pedido al panel
        } catch (\Throwable $e) {
        }

        return ['code' => $code, 'total' => $h['TOTPCL'], 'lines' => count($lines), 'tpl' => $last];
    }

    /** Borra de FactuSOL un pedido creado desde el panel (solo si nadie lo ha servido todavía). */
    public function remove(Quote $quote): void
    {
        $code = (int) $quote->fs_order_code;
        if (! $code) {
            throw new RuntimeException('Este presupuesto no tiene pedido en FactuSOL.');
        }
        $api = new FactusolClient;
        $year = (int) date('Y');
        $h = $api->table($year, 'F_PCL', "CODPCL = $code")[0] ?? null;
        if ($h) {
            if (! str_starts_with((string) ($h['REFPCL'] ?? ''), 'Panel ')) {
                throw new RuntimeException("El pedido $code no lo creó el panel; no lo borro.");
            }
            if ((int) $h['ESTPCL'] !== 0) {
                throw new RuntimeException("El pedido $code ya tiene mercancía servida; quita antes sus albaranes.");
            }
        }
        $api->delete($year, 'F_LPC', "CODLPC = $code");
        $api->delete($year, 'F_PCL', "CODPCL = $code");
        if (count($api->table($year, 'F_PCL', "CODPCL = $code")) + count($api->table($year, 'F_LPC', "CODLPC = $code"))) {
            throw new RuntimeException("No se ha borrado del todo el pedido $code; revísalo en FactuSOL.");
        }
        \App\Models\FsDocLine::whereIn('doc_id', \App\Models\FsDoc::where('type', 'pedido')->where('year', $year)->where('code', $code)->pluck('id'))->delete();
        \App\Models\FsDoc::where('type', 'pedido')->where('year', $year)->where('code', $code)->delete();
        $quote->update(['fs_order_code' => null, 'status' => 'borrador']);
        Activity::log('pedido_quitado_factusol', $quote->number.' ← '.$code);
    }
}
