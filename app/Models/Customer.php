<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $guarded = [];

    protected $casts = ['synced_at' => 'datetime', 'factusol_created_at' => 'datetime'];

    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class)->latest();
    }

    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class)->orderBy('brand')->orderBy('model');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(CustomerAlert::class)->where('active', true)->orderByRaw("CASE kind WHEN 'pago' THEN 0 WHEN 'descuento' THEN 1 WHEN 'facturacion' THEN 2 ELSE 3 END");
    }

    public function paymentTerms(): ?string
    {
        if (! $this->payment_code) {
            return null;
        }
        static $map = null;
        $map ??= \Illuminate\Support\Facades\DB::table('payment_terms')->pluck('name', 'code')->all();

        return $map[$this->payment_code] ?? null;
    }

    /** Documentos de FactuSOL (pedidos, albaranes, facturas) de este cliente. */
    public function docs(): HasMany
    {
        return $this->hasMany(FsDoc::class, 'codcli', 'codcli')->orderByDesc('date')->orderByDesc('code');
    }

    public function displayName(): string
    {
        return $this->trade_name && $this->trade_name !== $this->name ? $this->trade_name : $this->name;
    }

    public function bestPhone(): ?string
    {
        return $this->mobile ?: $this->phone;
    }
}
