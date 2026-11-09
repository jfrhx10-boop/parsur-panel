<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Albarán creado en FactuSOL desde el panel a partir de lo recibido de un pedido. */
class PanelAlbaran extends Model
{
    protected $table = 'panel_albaranes';

    protected $guarded = [];

    protected $casts = ['removed_at' => 'datetime'];

    public function linesArray(): array
    {
        return (array) json_decode((string) $this->lines, true);
    }
}
