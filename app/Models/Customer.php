<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $guarded = [];

    protected $casts = ['synced_at' => 'datetime', 'factusol_created_at' => 'datetime', 'fleet_imported_at' => 'datetime', 'portal_last_at' => 'datetime', 'portal_locked_until' => 'datetime', 'portal_tour_at' => 'datetime'];

    /** Columnas de búsqueda normalizadas (sin tildes) a partir de los datos del cliente. */
    public static function searchFields(array $r): array
    {
        $n = fn ($v) => \App\Services\ArticleCategories::normalize((string) ($v ?? ''));
        $name = trim($n($r['name'] ?? '').' '.$n($r['trade_name'] ?? ''));

        return [
            'search_name' => mb_substr($name, 0, 255) ?: null,
            'search' => trim(implode(' ', array_filter([$name, $n($r['nif'] ?? ''), $n($r['phone'] ?? ''), $n($r['mobile'] ?? ''), $n($r['email'] ?? ''), $n($r['city'] ?? ''), $n($r['province'] ?? '')]))) ?: null,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Customer $c) {
            if ($c->isDirty(['name', 'trade_name', 'nif', 'phone', 'mobile', 'email', 'city', 'province']) || ! $c->search_name) {
                $c->forceFill(self::searchFields($c->getAttributes()));
            }
        });
    }

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
        return $this->hasMany(CustomerAlert::class)->where('active', true)->orderByRaw("CASE kind WHEN 'pago' THEN 0 WHEN 'descuento' THEN 1 WHEN 'facturacion' THEN 2 WHEN 'entrega' THEN 3 WHEN 'contacto' THEN 4 ELSE 5 END");
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

    public function portalContacts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PortalContact::class)->orderBy('kind')->orderBy('name');
    }

    public function portalOrders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PortalOrder::class)->orderByDesc('id');
    }

    public function portalUrl(): ?string
    {
        return $this->portal_token ? url('/portal/'.$this->portal_token) : null;
    }

    public function displayName(): string
    {
        // Nombre fiscal (razón social); si no lo tiene, el comercial.
        return trim((string) $this->name) !== '' ? $this->name : (string) $this->trade_name;
    }

    /** Nombre comercial si es distinto del fiscal (dato secundario). */
    public function tradeName(): ?string
    {
        return $this->trade_name && $this->trade_name !== $this->name ? $this->trade_name : null;
    }

    public function bestPhone(): ?string
    {
        return $this->mobile ?: $this->phone;
    }
}
