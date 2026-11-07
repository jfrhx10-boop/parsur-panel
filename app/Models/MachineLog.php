<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineLog extends Model
{
    protected $guarded = [];

    protected $casts = ['date' => 'date'];

    public const KINDS = ['horas' => 'Lectura de horas', 'mantenimiento' => 'Mantenimiento', 'averia' => 'Avería', 'nota' => 'Nota'];
}
