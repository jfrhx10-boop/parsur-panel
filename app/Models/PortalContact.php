<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalContact extends Model
{
    protected $guarded = [];

    public const KINDS = ['maquinista' => 'Maquinista', 'proveedor' => 'Proveedor', 'taller' => 'Taller / mecánico', 'otro' => 'Otro'];
}
