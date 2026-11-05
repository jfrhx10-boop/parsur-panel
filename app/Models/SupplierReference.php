<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierReference extends Model
{
    protected $guarded = [];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function consumption()
    {
        return $this->hasMany(SupplierConsumption::class);
    }
}
