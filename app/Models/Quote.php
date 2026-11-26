<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    protected $guarded = [];

    protected $casts = ['date' => 'date', 'valid_until' => 'date'];

    public const STATUSES = ['borrador' => 'Pendiente', 'enviado' => 'Enviado', 'aceptado' => 'Aceptado', 'rechazado' => 'Rechazado'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    private $partyCache = null;

    /**
     * Presupuestos sin ficha de cliente (en FactuSOL solo llevan un nombre o un teléfono): cuelgan de una ficha genérica,
     * pero se muestran con el nombre y el teléfono que se escribió en el presupuesto.
     */
    public function getCustomerAttribute()
    {
        $c = $this->getRelationValue('customer');
        if (! $c || ! $this->customer_name) {
            return $c;
        }
        if (! $this->partyCache || $this->partyCache->name !== $this->customer_name) {
            $p = clone $c;
            $p->name = $this->customer_name;
            $p->trade_name = null;
            $tel = preg_match('/(\+?\d[\d\s.\-]{7,}\d)/', (string) $this->customer_name, $m) ? preg_replace('/\D+/', '', $m[1]) : '';
            $p->mobile = strlen($tel) >= 9 ? $tel : null;
            $p->phone = null;
            $this->partyCache = $p;
        }

        return $this->partyCache;
    }

    public function noClient(): bool
    {
        return (bool) $this->customer_name;
    }

    /** Ficha genérica de la que cuelgan los presupuestos sin cliente (se crea la primera vez). */
    public static function noClientId(): int
    {
        $id = (int) \App\Models\Setting::get('nocli_customer_id');
        if ($id && Customer::whereKey($id)->exists()) {
            return $id;
        }
        $c = Customer::create(['name' => 'SIN FICHA DE CLIENTE (presupuestos a un teléfono o nombre suelto)']);
        \App\Models\Setting::put('nocli_customer_id', (string) $c->id);

        return (int) $c->id;
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('pos');
    }

    /** Días que se espera antes de reclamar un presupuesto y entre un recordatorio y el siguiente. */
    public const FOLLOW_AFTER = 3;

    public const FOLLOW_GAP = 5;

    public const FOLLOW_MAX = 2;

    /**
     * Presupuestos que toca reclamar: sin respuesta (ni aceptados ni rechazados), sin pedido, con importe, de hace entre 3 y 45 días,
     * que no se han reclamado todavía o se reclamaron hace más de 5 días, como mucho dos veces.
     */
    public function scopeToFollow($q)
    {
        return $q->where('kind', 'presupuesto')->whereIn('status', ['borrador', 'enviado'])->whereNull('fs_order_code')
            ->where('total', '>', 0)->where('follow_off', false)->where('follow_n', '<', self::FOLLOW_MAX)
            ->whereDate('date', '<=', now()->subDays(self::FOLLOW_AFTER)->toDateString())
            ->whereDate('date', '>=', now()->subDays(45)->toDateString())
            ->where(fn ($w) => $w->whereNull('followed_at')->orWhere('followed_at', '<=', now()->subDays(self::FOLLOW_GAP)))
            // Ya vendido aunque el presupuesto se quedara «pendiente»: el cliente tiene después un albarán o factura por el mismo importe
            ->whereRaw("NOT EXISTS (SELECT 1 FROM fs_docs d JOIN customers c ON c.codcli = d.codcli WHERE c.id = quotes.customer_id AND d.type IN ('albaran','factura') AND d.date >= quotes.date AND ABS(d.total - quotes.total) <= GREATEST(1, quotes.total * 0.02))");
    }

    /** Teléfono para WhatsApp (con el 34 delante), o null si no hay un móvil válido. */
    public function waPhone(): ?string
    {
        $tel = preg_replace('/\D+/', '', (string) ($this->customer?->mobile ?: $this->customer?->phone));
        if (strlen($tel) === 9) {
            $tel = '34'.$tel;
        }

        return strlen($tel) >= 11 ? $tel : null;
    }

    /** Mensaje de recordatorio listo para enviar por WhatsApp. */
    public function followText(?string $from = null): string
    {
        $quien = $from ? 'soy '.$from.' de Recambios Parsur' : 'te escribimos de Recambios Parsur';
        $para = trim((string) $this->ref) !== '' ? ' ('.trim((string) $this->ref).')' : '';
        $otra = $this->follow_n > 0 ? 'Te vuelvo a escribir por' : 'Te escribo por';

        return 'Hola, '.$quien.'. '.$otra.' el presupuesto '.$this->number.$para.' del '.$this->date?->format('d/m/Y').', por '.eur($this->total)
            .' con IVA. ¿Has podido verlo? Si te encaja lo dejamos pedido hoy mismo, y si necesitas cambiar algo dímelo y lo ajusto.';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusClass(): string
    {
        return match ($this->status) { 'aceptado' => 'ok', 'rechazado' => 'bad', 'enviado' => '', default => 'warn' };
    }

    public static function nextNumber(string $kind = 'presupuesto'): string
    {
        $y = date('Y');
        $pre = ['pedido' => 'PED', 'albaran' => 'ALB'][$kind] ?? 'P';
        $last = static::where('number', 'like', "$pre-$y-%")->orderByDesc('id')->value('number');
        $n = $last ? (int) substr($last, -4) + 1 : 1;

        return sprintf('%s-%s-%04d', $pre, $y, $n);
    }

    public function isDraftDoc(): bool
    {
        return in_array($this->kind, ['pedido', 'albaran'], true);
    }

    public function recalc(): void
    {
        $base = 0;
        foreach ($this->lines as $l) {
            $t = round($l->qty * $l->price * (1 - $l->discount / 100), 2);
            if (abs((float) $l->total - $t) > 0.004) {
                $l->update(['total' => $t]);
            }
            $base += $t;
        }
        $iva = round($base * $this->iva_pct / 100, 2);
        $this->update(['base' => round($base, 2), 'iva' => $iva, 'total' => round($base + $iva, 2)]);
    }
}
