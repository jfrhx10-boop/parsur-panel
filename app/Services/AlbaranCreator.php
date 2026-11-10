<?php

namespace App\Services;

use App\Models\FsDoc;
use App\Models\FsDocLine;
use App\Models\OrderLineReceipt;
use App\Models\PanelAlbaran;
use RuntimeException;

/**
 * Crea en FactuSOL un albarán de cliente con lo que ha llegado de un pedido (aunque sea parcial):
 * inserta F_ALB + F_LAL (enlazado al pedido), descuenta lo servido en F_LPC.PENLPC y actualiza el estado del pedido.
 * La cabecera parte del último albarán real como plantilla; cliente, condiciones e importes salen del pedido.
 */
class AlbaranCreator
{
    private const HDR_COPY = ['CLI', 'CNO', 'CDO', 'CPO', 'CCP', 'CPR', 'CNI', 'TEL', 'CEM', 'AGE', 'TIV', 'REQ', 'ALM', 'FOP',
        'PIVA1', 'PIVA2', 'PIVA3', 'PREC1', 'PREC2', 'PREC3', 'TIVA1', 'TIVA2', 'TIVA3', 'PRO'];

    private function f(array $row, string $k)
    {
        return $row[$k] ?? null;
    }

    public function create(FsDoc $doc): array
    {
        $fsLock = FsLock::hold('F_ALB');
        $api = new FactusolClient;
        $year = (int) $doc->year;
        $code = (int) $doc->code;

        $ph = $api->table($year, 'F_PCL', "CODPCL = $code")[0] ?? null;
        $pl = $api->table($year, 'F_LPC', "CODLPC = $code");
        if (! $ph || ! $pl) {
            throw new RuntimeException("No se ha podido leer el pedido $code de FactuSOL.");
        }
        if ((int) $ph['ESTPCL'] === 4) {
            throw new RuntimeException('El pedido está anulado.');
        }
        foreach (['PDTO', 'PPPA', 'PPOR', 'PFIN', 'IDTO', 'IPPA', 'IPOR', 'IFIN'] as $p) {
            foreach ([1, 2, 3, 4] as $n) {
                if (abs((float) ($ph["$p{$n}PCL"] ?? 0)) > 0.0001) {
                    throw new RuntimeException('El pedido lleva descuentos o recargos globales; haz este albarán en FactuSOL para no descuadrar importes.');
                }
            }
        }

        // Lo recibido en el panel, limitado a lo que sigue pendiente en FactuSOL
        $rec = OrderLineReceipt::where('doc_id', $doc->id)->pluck('received', 'pos');
        $todo = [];
        foreach ($pl as $l) {
            $pos = (int) $l['POSLPC'];
            $pend = max(0.0, (float) $l['PENLPC']);
            $qty = min($pend, (float) ($rec[$pos] ?? 0));
            if ($qty > 0) {
                $todo[$pos] = ['row' => $l, 'qty' => $qty, 'pend' => $pend];
            }
        }
        if (! $todo) {
            throw new RuntimeException('No hay nada recibido pendiente de servir en este pedido.');
        }

        // Plantilla: último albarán
        $max = $api->query($year, 'SELECT MAX(CODALB) AS M FROM F_ALB');
        $last = (int) ($max[0]['M'] ?? 0);
        $th = $last ? ($api->table($year, 'F_ALB', "CODALB = $last")[0] ?? null) : null;
        $tl = $last ? ($api->table($year, 'F_LAL', "CODLAL = $last")[0] ?? null) : null;
        if (! $th || ! $tl) {
            throw new RuntimeException('No hay un albarán anterior en FactuSOL que sirva de plantilla.');
        }
        $new = $last + 1;
        $tip = (string) $ph['TIPPCL'];

        // Líneas e importes
        $lines = [];
        $bases = [1 => 0.0, 2 => 0.0, 3 => 0.0];
        $pos = 0;
        foreach ($todo as $ppos => $t) {
            $src = $t['row'];
            $row = $tl;
            foreach ($src as $k => $v) {
                if (str_ends_with($k, 'LPC') && array_key_exists(substr($k, 0, -3).'LAL', $row)) {
                    $row[substr($k, 0, -3).'LAL'] = $v;
                }
            }
            $dto = (float) ($src['DT1LPC'] ?? 0);
            $tot = round($t['qty'] * (float) $src['PRELPC'] * (1 - $dto / 100), 2);
            $row['CODLAL'] = $new;
            $row['POSLAL'] = ++$pos;
            $row['CANLAL'] = $t['qty'];
            $row['TOTLAL'] = $tot;
            $row['DOCLAL'] = 'C';
            $row['DTPLAL'] = $tip;
            $row['DCOLAL'] = $code;
            $row['PIVLAL'] = (float) ($src['PIVLPC'] ?? 0) > 0 ? (float) $src['PIVLPC'] : 0;
            $row['TIVLAL'] = $row['PIVLAL'] > 0 ? round($t['qty'] * $row['PIVLAL'], 2) : 0;
            $lines[$ppos] = $row;
            $n = min(3, max(1, (int) ($src['IVALPC'] ?? 0) + 1));
            $bases[$n] += $tot;
        }
        $net = round(array_sum($bases), 2);

        // Cabecera
        $h = $th;
        foreach ($h as $k => $v) {
            if (preg_match('/^(NET|BAS|IIVA|IREC|IRET|IDTO|IPPA|IPOR|IFIN|PDTO|PPPA|PPOR|PFIN)\d?[A-Z]*ALB$|^TOTALB$/', $k) && is_numeric($v) && ! preg_match('/^(PIVA|PREC)/', $k)) {
                $h[$k] = 0;
            }
        }
        foreach (['OB1', 'OB2', 'REF', 'PED', 'PAS', 'TRZ', 'COM', 'TPO'] as $p) {
            if (array_key_exists($p.'ALB', $h)) {
                $h[$p.'ALB'] = '';
            }
        }
        foreach (self::HDR_COPY as $p) {
            if (array_key_exists($p.'PCL', $ph) && array_key_exists($p.'ALB', $h)) {
                $h[$p.'ALB'] = $ph[$p.'PCL'];
            }
        }
        $today = now('Europe/Madrid');
        $h['CODALB'] = $new;
        $h['TIPALB'] = $tip;
        $h['FECALB'] = $today->format('Y-m-d').'T00:00:00';
        $h['HORALB'] = '1900-01-01T'.$today->format('H:i:s');
        $h['ESTALB'] = 0;
        $h['REFALB'] = (string) ($ph['REFPCL'] ?? '');
        $h['PEDALB'] = $tip.'-'.str_pad((string) $code, 6, '0', STR_PAD_LEFT);
        $h['OB1ALB'] = 'Albarán creado desde el panel (pedido '.$code.')';
        $req = (int) ($ph['REQPCL'] ?? 0) === 1;
        $iva = 0.0;
        $rec2 = 0.0;
        foreach ($bases as $n => $b) {
            if ($b == 0.0) {
                continue;
            }
            $pi = (float) ($ph["PIVA{$n}PCL"] ?? 0);
            $pr = (float) ($ph["PREC{$n}PCL"] ?? 0);
            $h["NET{$n}ALB"] = round($b, 2);
            $h["BAS{$n}ALB"] = round($b, 2);
            $h["IIVA{$n}ALB"] = round($b * $pi / 100, 2);
            $h["IREC{$n}ALB"] = $req ? round($b * $pr / 100, 2) : 0;
            $iva += $h["IIVA{$n}ALB"];
            $rec2 += $h["IREC{$n}ALB"];
        }
        $total = round($net + $iva + $rec2, 2);
        $h['TOTALB'] = $total;

        $api->insert($year, 'F_ALB', $h);
        foreach ($lines as $row) {
            $api->insert($year, 'F_LAL', $row);
        }

        // Pedido: descontar lo servido y actualizar estado
        $allDone = true;
        foreach ($pl as $l) {
            $ppos = (int) $l['POSLPC'];
            $pend = max(0.0, (float) $l['PENLPC']);
            if (isset($todo[$ppos])) {
                $pend = round($pend - $todo[$ppos]['qty'], 4);
                $api->update($year, 'F_LPC', ['TIPLPC' => $tip, 'CODLPC' => $code, 'POSLPC' => $ppos, 'PENLPC' => $pend]);
            }
            if ($pend > 0.0001) {
                $allDone = false;
            }
        }
        $estado = $allDone ? 2 : 1;
        $api->update($year, 'F_PCL', ['TIPPCL' => $tip, 'CODPCL' => $code, 'ESTPCL' => $estado]);

        // Comprobación
        $chk = $api->table($year, 'F_ALB', "CODALB = $new")[0] ?? null;
        $nl = count($api->table($year, 'F_LAL', "CODLAL = $new"));
        if (! $chk || $nl !== count($lines)) {
            throw new RuntimeException("Albarán $new escrito, pero la comprobación no cuadra (cabecera ".($chk ? 'sí' : 'NO').", líneas $nl de ".count($lines).'). Revísalo en FactuSOL.');
        }

        // Copia local
        foreach ($todo as $ppos => $t) {
            FsDocLine::where('doc_id', $doc->id)->where('pos', $ppos)->update(['pending' => round($t['pend'] - $t['qty'], 4)]);
            OrderLineReceipt::where('doc_id', $doc->id)->where('pos', $ppos)->delete();
        }
        $doc->update(['status' => $estado]);
        PanelAlbaran::create([
            'year' => $year, 'albaran_code' => $new, 'pedido_code' => $code,
            'lines' => json_encode(array_map(fn ($t) => $t['qty'], $todo)), 'total' => $total, 'user_id' => auth()->id(),
        ]);
        Activity::log('albaran_desde_pedido', "alb $new ← pedido $code");

        $this->pull();

        return ['code' => $new, 'lines' => count($lines), 'total' => $total, 'estado' => $estado, 'tpl' => $last];
    }

    /** Albarán directo (venta sin pedido): se crea en FactuSOL a partir de un borrador del panel. */
    public function createFromQuote(\App\Models\Quote $quote): array
    {
        $fsLock = FsLock::hold('F_ALB');
        $quote->load(['customer', 'lines']);
        if ($quote->lines->isEmpty()) {
            throw new RuntimeException('El albarán no tiene líneas.');
        }
        $codcli = (int) $quote->customer->codcli;
        if ($codcli <= 0) {
            throw new RuntimeException('El cliente no existe en FactuSOL (es un cliente solo del panel).');
        }
        $api = new FactusolClient;
        $year = (int) date('Y');
        $terms = FsTerms::of($api, $year, $codcli);
        $cli = $terms['cli'];

        $max = $api->query($year, 'SELECT MAX(CODALB) AS M FROM F_ALB');
        $last = (int) ($max[0]['M'] ?? 0);
        $th = $last ? ($api->table($year, 'F_ALB', "CODALB = $last")[0] ?? null) : null;
        $tl = $last ? ($api->table($year, 'F_LAL', "CODLAL = $last")[0] ?? null) : null;
        if (! $th || ! $tl) {
            throw new RuntimeException('No hay un albarán anterior en FactuSOL que sirva de plantilla.');
        }
        $new = $last + 1;

        $net = 0.0;
        $lines = [];
        $pos = 0;
        foreach ($quote->lines as $l) {
            $a = $l->art ? \App\Models\Article::where('codart', $l->art)->first() : null;
            $tot = round($l->qty * $l->price * (1 - $l->discount / 100), 2);
            $net += $tot;
            $row = $tl;
            $row['CODLAL'] = $new;
            $row['POSLAL'] = ++$pos;
            $row['ARTLAL'] = (string) ($l->art ?? '');
            $row['DESLAL'] = mb_substr((string) $l->description, 0, 100);
            $row['CANLAL'] = (float) $l->qty;
            $row['DT1LAL'] = (float) $l->discount;
            $row['DT2LAL'] = 0;
            $row['DT3LAL'] = 0;
            $row['PRELAL'] = (float) $l->price;
            $row['TOTLAL'] = $tot;
            $row['IVALAL'] = 0;
            $row['DOCLAL'] = '';
            $row['DTPLAL'] = '';
            $row['DCOLAL'] = 0;
            $row['COSLAL'] = $a ? (float) $a->cost : 0;
            $row['PIVLAL'] = 0;
            $row['TIVLAL'] = 0;
            foreach (['MEMLAL', 'EJELAL', 'CE1LAL', 'CE2LAL', 'IMALAL', 'SUMLAL'] as $k) {
                if (array_key_exists($k, $row)) {
                    $row[$k] = '';
                }
            }
            $lines[] = $row;
        }
        $net = round($net, 2);
        $pct = (float) ($th['PIVA1ALB'] ?? 21);
        $iva = round($net * $pct / 100, 2);
        $total = round($net + $iva, 2);

        $h = $th;
        foreach ($h as $k => $v) {
            if (preg_match('/^(NET|BAS|IIVA|IREC|IRET|IDTO|IPPA|IPOR|IFIN|PDTO|PPPA|PPOR|PFIN)\d?[A-Z]*ALB$|^TOTALB$/', $k) && is_numeric($v)) {
                $h[$k] = 0;
            }
        }
        foreach (['OB1', 'OB2', 'REF', 'PED', 'PAS', 'TRZ', 'COM', 'TPO'] as $p) {
            if (array_key_exists($p.'ALB', $h)) {
                $h[$p.'ALB'] = '';
            }
        }
        $today = now('Europe/Madrid');
        $h['CODALB'] = $new;
        $h['FECALB'] = $today->format('Y-m-d').'T00:00:00';
        $h['HORALB'] = '1900-01-01T'.$today->format('H:i:s');
        $h['ESTALB'] = 0;
        $h['CLIALB'] = $codcli;
        $h['CNOALB'] = (string) ($cli['NOFCLI'] ?? $quote->customer->displayName());
        $h['CDOALB'] = (string) ($cli['DOMCLI'] ?? '');
        $h['CPOALB'] = (string) ($cli['POBCLI'] ?? '');
        $h['CCPALB'] = (string) ($cli['CPOCLI'] ?? '');
        $h['CPRALB'] = (string) ($cli['PROCLI'] ?? '');
        $h['CNIALB'] = (string) ($cli['NIFCLI'] ?? '');
        $h['TELALB'] = (string) ($cli['TELCLI'] ?? '');
        $h['CEMALB'] = (string) ($cli['EMACLI'] ?? '');
        $h['FOPALB'] = $terms['fop'];
        $h['AGEALB'] = $terms['age'];
        $h['REQALB'] = 0;
        $h['TIVALB'] = 0;
        $h['REFALB'] = mb_substr((string) $quote->ref, 0, 20);
        $h['OB1ALB'] = 'Albarán creado desde el panel';
        $h['NET1ALB'] = $net;
        $h['BAS1ALB'] = $net;
        $h['IIVA1ALB'] = $iva;
        $h['TOTALB'] = $total;

        $api->insert($year, 'F_ALB', $h);
        foreach ($lines as $row) {
            $api->insert($year, 'F_LAL', $row);
        }
        $chk = $api->table($year, 'F_ALB', "CODALB = $new")[0] ?? null;
        $nl = count($api->table($year, 'F_LAL', "CODLAL = $new"));
        if (! $chk || $nl !== count($lines)) {
            throw new RuntimeException("Albarán $new escrito, pero la comprobación no cuadra (cabecera ".($chk ? 'sí' : 'NO').", líneas $nl de ".count($lines).'). Revísalo en FactuSOL.');
        }
        PanelAlbaran::create(['year' => $year, 'albaran_code' => $new, 'pedido_code' => 0, 'lines' => '[]', 'total' => $total, 'user_id' => auth()->id()]);
        Activity::log('albaran_directo', "alb $new ← {$quote->number}");

        $this->pull();

        return ['code' => $new, 'lines' => count($lines), 'total' => $total];
    }

    /** Trae ya a la lista del panel el albarán (y el estado del pedido) que acabamos de tocar en FactuSOL. */
    private function dropLocal(int $year, int $alb): void
    {
        $d = FsDoc::where('type', 'albaran')->where('year', $year)->where('code', $alb)->first();
        if ($d) {
            FsDocLine::where('doc_id', $d->id)->delete();
            $d->delete();
        }
    }

    private function pull(): void
    {
        try {
            (new FactusolDocs)->quick(['albaran'], 10, true); // el pedido ya queda actualizado en el panel por el propio alta
        } catch (\Throwable $e) {
        }
    }

    /** Deshace un albarán creado por el panel: lo borra de FactuSOL y devuelve lo servido al pedido. */
    public function remove(PanelAlbaran $pa): void
    {
        if ($pa->removed_at) {
            throw new RuntimeException('Este albarán ya se quitó.');
        }
        $api = new FactusolClient;
        $year = (int) $pa->year;
        $alb = (int) $pa->albaran_code;
        $code = (int) $pa->pedido_code;
        $h = $api->table($year, 'F_ALB', "CODALB = $alb")[0] ?? null;
        if ($h && (int) ($h['ESTALB'] ?? 0) !== 0) {
            throw new RuntimeException("El albarán $alb ya está facturado; no lo toco. Anúlalo en FactuSOL.");
        }
        if ($h && ! str_contains((string) ($h['OB1ALB'] ?? ''), 'reado desde el panel')) {
            throw new RuntimeException("El albarán $alb no lo creó el panel; no lo borro.");
        }
        if ($code === 0) { // albarán directo, sin pedido
            $api->delete($year, 'F_LAL', "CODLAL = $alb");
            $api->delete($year, 'F_ALB', "CODALB = $alb");
            if (count($api->table($year, 'F_ALB', "CODALB = $alb")) + count($api->table($year, 'F_LAL', "CODLAL = $alb"))) {
                throw new RuntimeException("No se ha borrado del todo el albarán $alb; revísalo en FactuSOL.");
            }
            $pa->update(['removed_at' => now()]);
            $this->dropLocal($year, $alb);
            Activity::log('albaran_quitado', "alb $alb (directo)");

            return;
        }
        $pl = $api->table($year, 'F_LPC', "CODLPC = $code");
        $tip = (string) ($pl[0]['TIPLPC'] ?? '1');
        $all = true;
        $back = $pa->linesArray();
        foreach ($pl as $l) {
            $ppos = (int) $l['POSLPC'];
            $pend = (float) $l['PENLPC'];
            if (isset($back[$ppos])) {
                $pend = round(min((float) $l['CANLPC'], $pend + (float) $back[$ppos]), 4);
                $api->update($year, 'F_LPC', ['TIPLPC' => $tip, 'CODLPC' => $code, 'POSLPC' => $ppos, 'PENLPC' => $pend]);
            }
            if ($pend < (float) $l['CANLPC'] - 0.0001) {
                $all = false;
            }
        }
        $served = ! $all; // si queda algo servido, parcial; si nada, pendiente
        $anyPending = collect($pl)->contains(fn ($l) => true);
        $api->delete($year, 'F_LAL', "CODLAL = $alb");
        $api->delete($year, 'F_ALB', "CODALB = $alb");
        $api->update($year, 'F_PCL', ['TIPPCL' => $tip, 'CODPCL' => $code, 'ESTPCL' => $served ? 1 : 0]);
        $left = count($api->table($year, 'F_ALB', "CODALB = $alb")) + count($api->table($year, 'F_LAL', "CODLAL = $alb"));
        if ($left) {
            throw new RuntimeException("No se ha borrado del todo el albarán $alb; revísalo en FactuSOL.");
        }
        $pa->update(['removed_at' => now()]);
        $fs = FsDoc::where('type', 'pedido')->where('year', $year)->where('code', $code)->first();
        if ($fs) {
            $fs->update(['status' => $served ? 1 : 0]);
            foreach ($back as $ppos => $q) {
                FsDocLine::where('doc_id', $fs->id)->where('pos', $ppos)->increment('pending', (float) $q);
            }
        }
        $this->dropLocal($year, $alb);
        Activity::log('albaran_quitado', "alb $alb de pedido $code");
    }
}
