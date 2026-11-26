<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Illuminate\Http\Request;

/**
 * Enlaces de solo lectura para automatizaciones externas (Zapier).
 * Se protegen con un token largo; aquí solo se guarda su huella SHA-256, nunca el token.
 */
class HooksController extends Controller
{
    private const TOKEN_SHA256 = '34a0eddf5683951bb1435cfe9959c0f7d8fefe8e991895b373406c9b08b874db';

    private static function allowed(string $token): bool
    {
        return hash_equals(self::TOKEN_SHA256, hash('sha256', $token));
    }

    /** Presupuestos que toca reclamar hoy, con el WhatsApp de recordatorio ya escrito. */
    public function reclamar(Request $request, string $token)
    {
        abort_unless(self::allowed($token), 404);
        $quotes = Quote::with('customer')->toFollow()->orderByDesc('date')->orderByDesc('id')->limit(60)->get();
        $items = $quotes->map(function (Quote $q) {
            $wa = $q->waPhone();
            $name = $q->customer?->displayName() ?? $q->customer_name ?? 'Sin ficha';

            return [
                'numero' => $q->number,
                'cliente' => $name,
                'fecha' => $q->date?->format('d/m/Y'),
                'dias' => (int) $q->date?->diffInDays(now()),
                'total' => round((float) $q->total, 2),
                'referencia' => (string) $q->ref,
                'reclamado_veces' => (int) $q->follow_n,
                'whatsapp' => $wa ? 'https://wa.me/'.$wa.'?text='.rawurlencode($q->followText('Juan')) : null,
                'panel' => url('/presupuestos/'.$q->id),
            ];
        })->values();
        $total = round($items->sum('total'), 2);
        $html = '<p>Buenos días, Juan. Hoy tienes <b>'.$items->count().'</b> presupuestos sin respuesta para reclamar, por <b>'.eur($total).'</b> con IVA.</p>';
        if ($items->isNotEmpty()) {
            $html .= '<table cellpadding="6" style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:14px">'
                .'<tr style="background:#161616;color:#F5B400"><th align="left">Presupuesto</th><th align="left">Cliente</th><th align="right">Días</th><th align="right">Total</th><th align="left">Acciones</th></tr>';
            foreach ($items as $i) {
                $acc = '<a href="'.e($i['panel']).'">Abrir</a>'.($i['whatsapp'] ? ' · <a href="'.e($i['whatsapp']).'">WhatsApp</a>' : ' · sin móvil');
                $html .= '<tr style="border-bottom:1px solid #ddd"><td>'.e($i['numero']).($i['referencia'] ? '<br><small>'.e($i['referencia']).'</small>' : '').'</td><td>'.e($i['cliente'])
                    .($i['reclamado_veces'] ? '<br><small>ya reclamado '.$i['reclamado_veces'].'×</small>' : '').'</td><td align="right">'.$i['dias'].'</td><td align="right">'.e(eur($i['total'])).'</td><td>'.$acc.'</td></tr>';
            }
            $html .= '</table><p style="font-size:12px;color:#666">Cuando lo reclames, pulsa «Reclamar» en el panel para que se apunte y no vuelva a salir hasta dentro de 5 días.</p>';
        }

        return response()->json(['count' => $items->count(), 'total' => $total, 'asunto' => 'Presupuestos para reclamar hoy: '.$items->count().' ('.eur($total).')', 'html' => $html, 'items' => $items]);
    }

    /**
     * PDF de albarán o factura de proveedor llegado al correo (lo manda Zapier con el enlace temporal del adjunto).
     * Se descarga, se guarda como documento «por leer» y el navegador lo lee al abrir «Subir albarán».
     */
    public function albaran(Request $request, string $token)
    {
        abort_unless(self::allowed($token), 404);
        $d = $request->validate([
            'url' => ['required', 'url', 'max:2000'], 'name' => ['nullable', 'string', 'max:200'],
            'from' => ['nullable', 'string', 'max:200'], 'subject' => ['nullable', 'string', 'max:300'],
        ]);
        $host = strtolower((string) parse_url($d['url'], PHP_URL_HOST));
        abort_unless($host === 'zapier.com' || str_ends_with($host, '.zapier.com') || str_ends_with($host, '.zapierusercontent.com'), 422, 'Solo enlaces de adjuntos de Zapier.');
        $res = \Illuminate\Support\Facades\Http::timeout(40)->withOptions(['allow_redirects' => ['max' => 5]])->get($d['url']);
        if (! $res->successful()) {
            return response()->json(['ok' => false, 'error' => 'No se ha podido descargar el adjunto ('.$res->status().')'], 502);
        }
        $raw = $res->body();
        if (strlen($raw) < 200 || strlen($raw) > 15 * 1048576) {
            return response()->json(['ok' => false, 'error' => 'Tamaño no válido: '.strlen($raw).' bytes'], 422);
        }
        if (strpos(substr($raw, 0, 1024), '%PDF') === false) {
            return response()->json(['ok' => true, 'skipped' => 'no es un PDF']);
        }
        $hash = sha1($raw);
        $seen = json_decode((string) \App\Models\Setting::get('mail_pdf_hashes', '[]'), true) ?: [];
        if (in_array($hash, $seen, true)) {
            return response()->json(['ok' => true, 'skipped' => 'ya estaba subido']);
        }
        $name = trim((string) ($d['name'] ?? '')) ?: 'documento.pdf';
        if (! str_ends_with(strtolower($name), '.pdf')) {
            $name .= '.pdf';
        }
        $path = 'albaranes-proveedor/'.date('Y/m').'/correo-'.substr($hash, 0, 12).'.pdf';
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, $raw);
        $note = SupplierNotesController::makeNote($path, $name, 'application/pdf', '', 'correo', 'auto', null);
        $note->update(['supplier_name' => mb_substr(trim(($d['from'] ?? '').($d['subject'] ? ' · '.$d['subject'] : '')), 0, 190) ?: null]);
        $seen[] = $hash;
        \App\Models\Setting::put('mail_pdf_hashes', json_encode(array_slice($seen, -400)));
        \App\Services\Activity::log('albaran_proveedor_correo', '#'.$note->id.' '.$name.' · '.($d['from'] ?? ''), null);

        return response()->json(['ok' => true, 'id' => $note->id]);
    }
}
