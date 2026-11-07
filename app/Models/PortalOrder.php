<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Pedido hecho por el cliente desde su portal. */
class PortalOrder extends Model
{
    protected $guarded = [];

    public const STATUSES = ['nuevo' => 'Nuevo', 'aceptado' => 'En preparación', 'servido' => 'Servido', 'rechazado' => 'No disponible'];

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
        return $this->hasMany(PortalOrderLine::class)->orderBy('id');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public static function nextNumber(): string
    {
        $y = date('Y');
        $last = self::where('number', 'like', "W-$y-%")->orderByDesc('id')->value('number');
        $n = $last ? (int) substr($last, -4) + 1 : 1;

        return sprintf('W-%s-%04d', $y, $n);
    }
}
