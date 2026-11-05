<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $guarded = [];

    protected $casts = ['imported_at' => 'datetime'];

    public function references()
    {
        return $this->hasMany(SupplierReference::class);
    }
}
