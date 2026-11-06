<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    protected $guarded = [];

    protected $casts = ['date' => 'date', 'valid_until' => 'date'];

    public const STATUSES = ['borrador' => 'Borrador', 'enviado' => 'Enviado', 'aceptado' => 'Aceptado', 'rechazado' => 'Rechazado'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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

    public static function nextNumber(): string
    {
        $y = date('Y');
        $last = static::where('number', 'like', "P-$y-%")->orderByDesc('id')->value('number');
        $n = $last ? (int) substr($last, -4) + 1 : 1;

        return sprintf('P-%s-%04d', $y, $n);
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
