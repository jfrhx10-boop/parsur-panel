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
