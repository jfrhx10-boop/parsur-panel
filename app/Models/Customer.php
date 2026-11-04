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

    public function displayName(): string
    {
        return $this->trade_name && $this->trade_name !== $this->name ? $this->trade_name : $this->name;
    }

    public function bestPhone(): ?string
    {
        return $this->mobile ?: $this->phone;
    }
}
