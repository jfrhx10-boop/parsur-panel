<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $guarded = [];

    protected $casts = ['factusol_created_at' => 'date', 'synced_at' => 'datetime'];

    /** La columna del proveedor habitual llega con una migración: hasta entonces no se toca. */
    public static function hasSupplierCol(): bool
    {
        static $v = null;

        return $v ??= \Illuminate\Support\Facades\Schema::hasColumn('articles', 'codpro');
    }

    public function marginPct(): ?float
    {
        if ($this->price <= 0 || $this->cost <= 0) {
            return null;
        }

        return round(($this->price - $this->cost) / $this->price * 100, 1);
    }
}
