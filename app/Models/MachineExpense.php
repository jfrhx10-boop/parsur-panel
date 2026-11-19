<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Gasto que el cliente apunta en una máquina (otros proveedores, talleres…), con su factura si la sube. */
class MachineExpense extends Model
{
    protected $guarded = [];

    protected $casts = ['date' => 'date'];

    /** Días que se conserva la foto o el PDF de la factura. Después se borra el archivo; el gasto (proveedor, concepto, importe) se queda. */
    public const FILE_DAYS = 3;

    /** Borra los archivos de factura con más de FILE_DAYS días. Devuelve cuántos ha borrado. */
    public static function purgeOldFiles(): int
    {
        $n = 0;
        foreach (self::whereNotNull('file')->where('created_at', '<=', now()->subDays(self::FILE_DAYS))->limit(200)->get() as $e) {
            try {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($e->file);
            } catch (\Throwable $x) {
            }
            $e->forceFill(['file' => null])->save();
            $n++;
        }

        return $n;
    }

    /** ¿Tuvo factura adjunta y ya se ha borrado? */
    public function filePurged(): bool
    {
        return ! $this->file && (bool) $this->file_name;
    }

    public function machine(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
