<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Revisión diaria de una máquina hecha desde el portal (Parsur Flota). */
class MachineCheck extends Model
{
    protected $guarded = [];

    protected $casts = ['date' => 'date', 'items' => 'array', 'photos' => 'array'];

    public const ITEMS = [
        'niveles' => 'Niveles: aceite, refrigerante e hidráulico',
        'fugas' => 'Fugas de aceite o de gasoil',
        'rodaje' => 'Rodaje, cadenas o ruedas',
        'luces' => 'Luces, rotativo, claxon y avisador',
        'estructura' => 'Cazo, bulones y piezas sueltas o rotas',
    ];

    public function machine(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /** Puntos marcados con fallo, con su texto. */
    public function faultLabels(): array
    {
        $out = [];
        foreach ((array) $this->items as $k => $v) {
            if ($v === 'mal' && isset(self::ITEMS[$k])) {
                $out[] = self::ITEMS[$k];
            }
        }

        return $out;
    }
}
