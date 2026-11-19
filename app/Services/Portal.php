<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\FsDoc;
use App\Models\Machine;
use Illuminate\Support\Collection;

/** Utilidades del portal de cliente: todo lo que se muestra va SIN referencias ni códigos. */
class Portal
{
    /** Quita de un texto lo que parezca una referencia (tokens con 3+ dígitos, códigos HIFI…). */
    public static function clean(?string $text): string
    {
        $t = (string) $text;
        $t = preg_replace('/\b[A-Z0-9][A-Z0-9\-\/\.]*\d{3,}[A-Z0-9\-\/\.]*\b/i', '', $t);
        $t = preg_replace('/\b\d+[A-Z]\d{2,}\b/i', '', $t);
        $t = preg_replace('/\s{2,}/', ' ', $t);

        return trim($t, " -·/,");
    }

    /** Lo que lleva una máquina, como etiquetas sin referencia (agrupadas: filtros / desgaste / recambios). */
    public static function machineItems(Machine $m): array
    {
        $out = [];
        foreach (PartsParser::items($m->parts) as $i) {
            $label = $i['label'];
            if ($i['filter'] && ! preg_match('/filtro/i', $label)) {
                $label = 'Filtro '.mb_strtolower($label);
            }
            $out[] = ['label' => $label, 'kind' => $i['kind'], 'ref' => $i['ref'], 'qty' => $i['qty']];
        }

        return $out;
    }

    /** Documentos del cliente (albaranes y facturas) con líneas limpias. */
    public static function docs(Customer $c, ?Machine $m = null): Collection
    {
        if (! $c->codcli) {
            return collect();
        }
        $q = FsDoc::where('codcli', $c->codcli)->whereIn('type', ['albaran', 'factura'])->with('lines')->orderByDesc('date')->orderByDesc('code');
        if ($m) {
            // Un documento es de una máquina si está asignado a ella, si su referencia lleva el modelo o,
            // cuando el cliente solo tiene una máquina en la flota, si no lleva ninguna referencia.
            $model = mb_strtoupper(trim($m->model));
            $unica = Machine::where('customer_id', $c->id)->count() === 1;
            $q->where(function ($w) use ($m, $model, $unica) {
                $w->where('machine_id', $m->id);
                if (mb_strlen($model) >= 3) {
                    $w->orWhere(fn ($x) => $x->whereNotNull('ref')->whereRaw('UPPER(ref) LIKE ?', ['%'.$model.'%']));
                }
                if ($unica) {
                    $w->orWhere(fn ($x) => $x->whereNull('machine_id')->where(fn ($y) => $y->whereNull('ref')->orWhere('ref', '')));
                }
            });
        }

        return $q->get();
    }

    /**
     * Compras del cliente: los albaranes; si no hay ninguno (clientes a los que se factura directamente), las facturas.
     * Nunca las dos cosas a la vez, para no contar dos veces la misma compra.
     */
    public static function purchases(Collection $docs): Collection
    {
        $alb = $docs->where('type', 'albaran');

        return $alb->isNotEmpty() ? $alb : $docs->where('type', 'factura');
    }

    /** Gasto por año (sobre las compras, base imponible). */
    public static function spendByYear(Collection $docs): array
    {
        $alb = self::purchases($docs);
        $out = [];
        foreach ($alb->groupBy('year')->sortKeys() as $y => $g) {
            $out[$y] = ['n' => $g->count(), 'total' => (float) $g->sum('base')];
        }

        return $out;
    }

    public static function spendByMachine(Customer $c): array
    {
        $out = [];
        foreach ($c->machines as $m) {
            $docs = self::purchases(self::docs($c, $m));
            $out[$m->id] = ['n' => $docs->count(), 'total' => (float) $docs->sum('base'), 'year' => (float) $docs->where('year', (int) date('Y'))->sum('base'), 'last' => $docs->first()?->date];
        }

        return $out;
    }
}
