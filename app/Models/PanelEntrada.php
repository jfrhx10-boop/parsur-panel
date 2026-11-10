<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Entrada de proveedor creada en FactuSOL desde el panel (para poder deshacerla). */
class PanelEntrada extends Model
{
    protected $table = 'panel_entradas';

    protected $guarded = [];

    protected $casts = ['removed_at' => 'datetime'];
}
