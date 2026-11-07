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
            $model = mb_strtoupper(trim($m->model));
            if (mb_strlen($model) < 3) {
                return collect();
            }
            $q->whereNotNull('ref')->whereRaw('UPPER(ref) LIKE ?', ['%'.$model.'%']);
        }

        return $q->get();
    }

    /** Gasto por año (sobre albaranes, base imponible). */
    public static function spendByYear(Collection $docs): array
    {
        $alb = $docs->where('type', 'albaran');
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
            $docs = self::docs($c, $m)->where('type', 'albaran');
            $out[$m->id] = ['n' => $docs->count(), 'total' => (float) $docs->sum('base'), 'year' => (float) $docs->where('year', (int) date('Y'))->sum('base'), 'last' => $docs->first()?->date];
        }

        return $out;
    }
}
