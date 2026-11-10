<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Albarán de proveedor subido al panel (PDF o foto) y lo que se ha leído de él. */
class SupplierNote extends Model
{
    protected $guarded = [];

    protected $casts = ['date' => 'date', 'received_at' => 'datetime'];

    /** @return array<int,array<string,mixed>> */
    public function linesArray(): array
    {
        return array_values((array) json_decode((string) $this->lines, true));
    }

    public function supplier(): ?FsSupplier
    {
        return $this->codpro ? FsSupplier::where('codpro', $this->codpro)->first() : null;
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function stateLabel(): string
    {
        if ($this->entry_code && $this->received_at) {
            return 'Entrada '.$this->entry_code.' · llegada validada';
        }
        if ($this->entry_code) {
            return 'Entrada '.$this->entry_code.' creada';
        }

        return $this->received_at ? 'Llegada validada' : 'Por revisar';
    }
}
