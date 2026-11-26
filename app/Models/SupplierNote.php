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

    public function isInvoice(): bool
    {
        return $this->kind === 'factura';
    }

    /** Nombre del documento que se crea en FactuSOL a partir de este. */
    public function docLabel(): string
    {
        return $this->isInvoice() ? 'Factura recibida' : 'Entrada';
    }

    public function stateLabel(): string
    {
        $doc = $this->docLabel();
        if ($this->pendingRead()) {
            return 'Llegó por correo · leyendo…';
        }
        if ($this->entry_code && $this->received_at) {
            return $doc.' '.$this->entry_code.' · recepción confirmada';
        }
        if ($this->entry_code) {
            return $doc.' '.$this->entry_code.' creada';
        }

        return $this->received_at ? 'Recepción confirmada' : 'Por revisar';
    }

    /** Llegó por correo (Zapier) y todavía no se ha leído: lo lee el navegador al abrir la lista. */
    public function pendingRead(): bool
    {
        return $this->how === 'correo';
    }
}
