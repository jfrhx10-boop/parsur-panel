<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineReminder extends Model
{
    protected $guarded = [];

    protected $casts = ['due_date' => 'date', 'done_at' => 'datetime'];

    public function machine(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /** vencido | proximo | ok según fecha (7 días) u horas (dentro de 50 h). */
    public function state(?int $hours): string
    {
        if ($this->due_date) {
            $days = (int) now()->startOfDay()->diffInDays($this->due_date, false);

            return $days < 0 ? 'vencido' : ($days <= 7 ? 'proximo' : 'ok');
        }
        if ($this->due_hours !== null && $hours !== null) {
            $left = $this->due_hours - $hours;

            return $left <= 0 ? 'vencido' : ($left <= 50 ? 'proximo' : 'ok');
        }

        return 'ok';
    }
}
