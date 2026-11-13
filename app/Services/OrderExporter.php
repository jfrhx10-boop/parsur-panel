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
        $fsLock = FsLock::hold('F_PCL');
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
        $terms = FsTerms::of($api, $year, $codcli);
        $cli = $terms['cli'];
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
        $h['FOPPCL'] = $terms['fop'];
        $h['AGEPCL'] = $terms['age'];
        $h['REQPCL'] = $terms['req'];
        $h['TIVPCL'] = $terms['tiv'];
        $h['CLIPCL'] = $codcli;
        $h['CNOPCL'] = (string) ($cli['NOFCLI'] ?? $quote->customer->displayName());
        $h['CDOPCL'] = (string) ($cli['DOMCLI'] ?? '');
        $h['CPOPCL'] = (string) ($cli['POBCLI'] ?? '');
        $h['CCPPCL'] = (string) ($cli['CPOCLI'] ?? '');
        $h['CPRPCL'] = (string) ($cli['PROCLI'] ?? '');
        $h['CNIPCL'] = (string) ($cli['NIFCLI'] ?? '');
        $h['TELPCL'] = (string) ($cli['TELCLI'] ?? '');
        $h['CEMPCL'] = (string) ($cli['EMACLI'] ?? '');
        $h['REFPCL'] = mb_substr((string) $quote->ref, 0, 20); // la máquina, como en FactuSOL
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
        $this->setQuoteState($api, $quote, 1); // en FactuSOL el presupuesto queda como Aceptado
        Activity::log('presupuesto_a_pedido_factusol', $quote->number.' → pedido '.$code);
        try {
            (new FactusolDocs)->run($year, 25); // trae el pedido al panel
        } catch (\Throwable $e) {
        }

        return ['code' => $code, 'total' => $h['TOTPCL'], 'lines' => count($lines), 'tpl' => $last];
    }

    /** Estado del presupuesto en FactuSOL (0 pendiente, 1 aceptado) y en la copia local. */
    private function setQuoteState(FactusolClient $api, Quote $quote, int $est): void
    {
        if (! $quote->fs_code) {
            return;
        }
        $y = (int) $quote->fs_year;
        $c = (int) $quote->fs_code;
        try {
            $h = $api->table($y, 'F_PRE', "CODPRE = $c")[0] ?? null;
            if ($h) {
                $api->update($y, 'F_PRE', ['TIPPRE' => $h['TIPPRE'], 'CODPRE' => $c, 'ESTPRE' => $est]);
            }
            \App\Models\FsDoc::where('type', 'presupuesto')->where('year', $y)->where('code', $c)->update(['status' => $est]);
        } catch (\Throwable $e) {
            \Log::warning('No se pudo marcar el presupuesto '.$c.' como '.$est.': '.$e->getMessage());
        }
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
            if (! str_contains((string) ($h['OB1PCL'] ?? ''), 'creado desde el panel')) {
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
        $quote->update(['fs_order_code' => null, 'status' => 'enviado']);
        $this->setQuoteState($api, $quote, 0);
        Activity::log('pedido_quitado_factusol', $quote->number.' ← '.$code);
    }

    /**
     * Añade una línea a un pedido de cliente que ya está en FactuSOL y recalcula su total.
     * Solo pedidos pendientes o parciales con un único tipo de IVA, sin recargo ni descuentos de cabecera.
     *
     * @return array{pos:int,art:string,total:float,order_total:float}
     */
    public function addLine(int $year, int $code, ?string $ref, float $qty, ?string $description = null, ?float $price = null): array
    {
        $api = new FactusolClient;
        $h = $api->table($year, 'F_PCL', "CODPCL = $code")[0] ?? null;
        if (! $h) {
            throw new RuntimeException("No encuentro el pedido $code en FactuSOL.");
        }
        if (! in_array((int) ($h['ESTPCL'] ?? 0), [0, 1], true)) {
            throw new RuntimeException("El pedido $code ya está servido o anulado: no se le añaden líneas.");
        }
        foreach (['BAS2PCL', 'BAS3PCL', 'PDTO1PCL', 'PPPA1PCL', 'PPOR1PCL', 'PFIN1PCL', 'IREC1PCL', 'IRET1PCL'] as $k) {
            if (abs((float) ($h[$k] ?? 0)) > 0.0001) {
                throw new RuntimeException("El pedido $code tiene descuentos, recargo, retención o varios tipos de IVA: añade la línea en FactuSOL.");
            }
        }
        if ((int) ($h['REQPCL'] ?? 0) === 1) {
            throw new RuntimeException("El pedido $code lleva recargo de equivalencia: añade la línea en FactuSOL.");
        }
        $tip = (int) ($h['TIPPCL'] ?? 1);
        $existing = array_values(array_filter($api->table($year, 'F_LPC', "CODLPC = $code"), fn ($r) => (int) ($r['TIPLPC'] ?? $tip) === $tip));
        if (! $existing) {
            throw new RuntimeException("El pedido $code no tiene ninguna línea que sirva de plantilla: añádela en FactuSOL.");
        }
        usort($existing, fn ($a, $b) => (int) $a['POSLPC'] <=> (int) $b['POSLPC']);
        $tl = end($existing);
        $pos = (int) $tl['POSLPC'] + 1;

        $ref = trim((string) $ref);
        $a = $ref !== '' ? \App\Http\Controllers\QuotesController::findArticle($ref) : null;
        if (! $a && $ref !== '') {
            $a = ArticleCreator::pullOnce($ref);
        }
        $description = trim((string) $description);
        if (! $a && $description === '') {
            throw new RuntimeException($ref !== '' ? "La referencia $ref no está en FactuSOL. Pon también la descripción y el precio, o créala antes como artículo." : 'Indica una referencia o una descripción.');
        }
        if ($price === null) {
            $tariff = \App\Models\Customer::where('codcli', (int) ($h['CLIPCL'] ?? 0))->value('tariff');
            $price = \App\Http\Controllers\QuotesController::priceFor($a, $tariff ? (int) $tariff : null);
        }
        $tot = round($qty * $price, 2);

        $row = $tl;
        foreach ($row as $k => $v) {
            if (preg_match('/^(MEM|CE1|CE2|IMA|SUM|EJE)LPC$/', $k)) {
                $row[$k] = '';
            }
        }
        $row['TIPLPC'] = $tip;
        $row['CODLPC'] = $code;
        $row['POSLPC'] = $pos;
        $row['ARTLPC'] = $a ? (string) $a->codart : mb_strtoupper($ref);
        $row['DESLPC'] = mb_substr($description !== '' ? $description : (string) ($a->description ?? $ref), 0, 100);
        $row['CANLPC'] = $qty;
        $row['PENLPC'] = $qty;
        $row['DT1LPC'] = 0;
        $row['DT2LPC'] = 0;
        $row['DT3LPC'] = 0;
        $row['PRELPC'] = $price;
        $row['TOTLPC'] = $tot;
        $row['DOCLPC'] = '';
        $row['DTPLPC'] = '';
        $row['DCOLPC'] = 0;
        $row['COSLPC'] = $a ? (float) $a->cost : 0;
        // el tipo de IVA de la línea se queda como el de la línea usada de plantilla; su importe se recalcula
        $piv = (float) ($tl['PIVLPC'] ?? 0);
        if (array_key_exists('TIVLPC', $row)) {
            $row['TIVLPC'] = $piv > 0 ? round($qty * $piv, 2) : 0;
        }
        $api->insert($year, 'F_LPC', $row);

        $lines = array_values(array_filter($api->table($year, 'F_LPC', "CODLPC = $code"), fn ($r) => (int) ($r['TIPLPC'] ?? $tip) === $tip));
        if (count($lines) !== count($existing) + 1) {
            throw new RuntimeException("La línea se ha enviado, pero el pedido $code tiene ".count($lines).' líneas en FactuSOL y esperaba '.(count($existing) + 1).'. Revísalo allí.');
        }
        $base = round(array_sum(array_map(fn ($r) => (float) $r['TOTLPC'], $lines)), 2);
        $pct = (float) ($h['PIVA1PCL'] ?? 21);
        $iva = round($base * $pct / 100, 2);
        $total = round($base + $iva, 2);
        $api->update($year, 'F_PCL', ['TIPPCL' => $tip, 'CODPCL' => $code, 'NET1PCL' => $base, 'BAS1PCL' => $base, 'IIVA1PCL' => $iva, 'TOTPCL' => $total]);
        Activity::log('pedido_linea_factusol', "pedido $code + {$row['ARTLPC']} x $qty a $price");
        $this->mirror($year, $code, $lines, $base, $total);

        return ['pos' => $pos, 'art' => (string) $row['ARTLPC'], 'total' => $tot, 'order_total' => $total];
    }

    /** Cabecera de un pedido abierto y sus líneas, con las mismas comprobaciones que al añadir una línea. */
    private function openOrder(FactusolClient $api, int $year, int $code, string $what): array
    {
        $h = $api->table($year, 'F_PCL', "CODPCL = $code")[0] ?? null;
        if (! $h) {
            throw new RuntimeException("No encuentro el pedido $code en FactuSOL.");
        }
        if (! in_array((int) ($h['ESTPCL'] ?? 0), [0, 1], true)) {
            throw new RuntimeException("El pedido $code ya está servido o anulado: no se puede $what.");
        }
        foreach (['BAS2PCL', 'BAS3PCL', 'PDTO1PCL', 'PPPA1PCL', 'PPOR1PCL', 'PFIN1PCL', 'IREC1PCL', 'IRET1PCL'] as $k) {
            if (abs((float) ($h[$k] ?? 0)) > 0.0001) {
                throw new RuntimeException("El pedido $code tiene descuentos, recargo, retención o varios tipos de IVA: hay que $what en FactuSOL.");
            }
        }
        if ((int) ($h['REQPCL'] ?? 0) === 1) {
            throw new RuntimeException("El pedido $code lleva recargo de equivalencia: hay que $what en FactuSOL.");
        }
        $tip = (int) ($h['TIPPCL'] ?? 1);
        $lines = array_values(array_filter($api->table($year, 'F_LPC', "CODLPC = $code"), fn ($r) => (int) ($r['TIPLPC'] ?? $tip) === $tip));

        return [$h, $tip, $lines];
    }

    /** Recalcula base, IVA y total de la cabecera a partir de las líneas que hay ahora en FactuSOL. */
    private function retotal(FactusolClient $api, int $year, int $code, int $tip, array $h): array
    {
        $lines = array_values(array_filter($api->table($year, 'F_LPC', "CODLPC = $code"), fn ($r) => (int) ($r['TIPLPC'] ?? $tip) === $tip));
        $base = round(array_sum(array_map(fn ($r) => (float) $r['TOTLPC'], $lines)), 2);
        $iva = round($base * (float) ($h['PIVA1PCL'] ?? 21) / 100, 2);
        $total = round($base + $iva, 2);
        $api->update($year, 'F_PCL', ['TIPPCL' => $tip, 'CODPCL' => $code, 'NET1PCL' => $base, 'BAS1PCL' => $base, 'IIVA1PCL' => $iva, 'TOTPCL' => $total]);
        $this->mirror($year, $code, $lines, $base, $total);

        return [$lines, $total];
    }

    /**
     * Refleja en la copia del panel el pedido tal como acaba de quedar en FactuSOL, sin lanzar la sincronización
     * general (que recorre todos los documentos y tarda muchos segundos).
     */
    private function mirror(int $year, int $code, array $lines, float $base, float $total): void
    {
        try {
            $doc = \App\Models\FsDoc::where('type', 'pedido')->where('year', $year)->where('code', $code)->first();
            if (! $doc) {
                return;
            }
            \Illuminate\Support\Facades\DB::transaction(function () use ($doc, $lines, $base, $total) {
                \App\Models\FsDocLine::where('doc_id', $doc->id)->delete();
                $ins = [];
                foreach ($lines as $r) {
                    $art = trim((string) ($r['ARTLPC'] ?? ''));
                    $des = trim((string) ($r['DESLPC'] ?? ''));
                    $ins[] = [
                        'doc_id' => $doc->id, 'pos' => (int) ($r['POSLPC'] ?? 0), 'art' => $art === '' ? null : $art,
                        'description' => $des === '' ? null : mb_substr($des, 0, 255),
                        'qty' => (float) ($r['CANLPC'] ?? 0), 'pending' => isset($r['PENLPC']) ? (float) $r['PENLPC'] : null,
                        'price' => (float) ($r['PRELPC'] ?? 0), 'discount' => (float) ($r['DT1LPC'] ?? 0), 'total' => (float) ($r['TOTLPC'] ?? 0),
                    ];
                }
                if ($ins) {
                    \App\Models\FsDocLine::insert($ins);
                }
                $doc->forceFill(['base' => $base, 'total' => $total, 'lines_count' => count($ins), 'synced_at' => now()])->save();
            });
        } catch (\Throwable $e) {
        }
    }

    /** Cambia la cantidad de una línea de un pedido abierto. No baja de lo ya servido. */
    public function setLineQty(int $year, int $code, int $pos, float $qty): array
    {
        $api = new FactusolClient;
        [$h, $tip, $lines] = $this->openOrder($api, $year, $code, 'cambiar la cantidad');
        $m = array_values(array_filter($lines, fn ($r) => (int) $r['POSLPC'] === $pos));
        if (count($m) !== 1) {
            throw new RuntimeException("No encuentro la línea $pos del pedido $code en FactuSOL.");
        }
        $l = $m[0];
        $old = (float) $l['CANLPC'];
        $served = max(0.0, $old - (float) ($l['PENLPC'] ?? $old));
        if ($qty < $served - 0.0001) {
            throw new RuntimeException('De esa línea ya se han servido '.rtrim(rtrim(number_format($served, 2, ',', ''), '0'), ',').' uds: la cantidad no puede ser menor.');
        }
        $bruto = $qty * (float) $l['PRELPC'];
        foreach (['DT1LPC', 'DT2LPC', 'DT3LPC'] as $k) {
            $bruto *= 1 - (float) ($l[$k] ?? 0) / 100;
        }
        $up = ['TIPLPC' => $tip, 'CODLPC' => $code, 'POSLPC' => $pos, 'CANLPC' => $qty, 'PENLPC' => max(0.0, $qty - $served), 'TOTLPC' => round($bruto, 2)];
        if (array_key_exists('TIVLPC', $l) && (float) ($l['PIVLPC'] ?? 0) > 0) {
            $up['TIVLPC'] = round($qty * (float) $l['PIVLPC'], 2);
        }
        $api->update($year, 'F_LPC', $up);
        [$after, $total] = $this->retotal($api, $year, $code, $tip, $h);
        $now = array_values(array_filter($after, fn ($r) => (int) $r['POSLPC'] === $pos))[0] ?? null;
        if (! $now || abs((float) $now['CANLPC'] - $qty) > 0.0001) {
            throw new RuntimeException("FactuSOL no ha guardado la cantidad de la línea $pos del pedido $code. Revísalo allí.");
        }
        Activity::log('pedido_linea_factusol', "pedido $code línea $pos {$l['ARTLPC']}: $old → $qty");

        return ['art' => (string) $l['ARTLPC'], 'old' => $old, 'total' => (float) $up['TOTLPC'], 'order_total' => $total];
    }

    /** Borra una línea de un pedido abierto. No borra líneas ya servidas ni la única línea del pedido. */
    public function removeLine(int $year, int $code, int $pos): array
    {
        $api = new FactusolClient;
        [$h, $tip, $lines] = $this->openOrder($api, $year, $code, 'borrar la línea');
        $m = array_values(array_filter($lines, fn ($r) => (int) $r['POSLPC'] === $pos));
        if (count($m) !== 1) {
            throw new RuntimeException("No encuentro la línea $pos del pedido $code en FactuSOL.");
        }
        $l = $m[0];
        if ((float) $l['CANLPC'] - (float) ($l['PENLPC'] ?? $l['CANLPC']) > 0.0001) {
            throw new RuntimeException('Esa línea ya está servida en todo o en parte: no se borra. Puedes bajar la cantidad hasta lo servido.');
        }
        if (count($lines) < 2) {
            throw new RuntimeException("Es la única línea del pedido $code: para quitarla, anula el pedido en FactuSOL.");
        }
        $api->delete($year, 'F_LPC', "TIPLPC = $tip AND CODLPC = $code AND POSLPC = $pos");
        [$after, $total] = $this->retotal($api, $year, $code, $tip, $h);
        if (count($after) !== count($lines) - 1) {
            throw new RuntimeException("He pedido borrar la línea, pero el pedido $code tiene ".count($after).' líneas en FactuSOL y esperaba '.(count($lines) - 1).'. Revísalo allí.');
        }
        Activity::log('pedido_linea_factusol', "pedido $code − línea $pos {$l['ARTLPC']} x {$l['CANLPC']}");

        return ['art' => (string) $l['ARTLPC'], 'qty' => (float) $l['CANLPC'], 'order_total' => $total];
    }
}
