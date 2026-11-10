<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Candado mientras se crea un documento en FactuSOL: el número nuevo sale de «el último + 1», así que dos altas
 * a la vez (dos personas, o un doble clic) no deben coger el mismo número. Se suelta solo al terminar el método.
 */
class FsLock
{
    private function __construct(private ?Lock $lock)
    {
    }

    public static function hold(string $table, int $wait = 20): self
    {
        $lock = Cache::lock('fs-new:'.$table, 90);
        try {
            $lock->block($wait);
        } catch (\Throwable $e) {
            throw new RuntimeException('FactuSOL está ocupado creando otro documento. Prueba otra vez en unos segundos.');
        }

        return new self($lock);
    }

    public function __destruct()
    {
        try {
            $this->lock?->release();
        } catch (\Throwable $e) {
        }
        $this->lock = null;
    }
}
