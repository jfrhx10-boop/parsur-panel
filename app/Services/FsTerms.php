<?php

namespace App\Services;

use RuntimeException;

/** Condiciones del cliente en FactuSOL (forma de pago, agente, recargo, IVA) para copiarlas a los documentos que crea el panel. */
class FsTerms
{
    /** @return array{cli:array,fop:string,age:int,req:int,tiv:int} */
    public static function of(FactusolClient $api, int $year, int $codcli): array
    {
        $cli = $api->record($year, 'F_CLI', "CODCLI = $codcli") ?? [];
        if ((int) ($cli['REQCLI'] ?? 0) === 1 || (int) ($cli['IVACLI'] ?? 0) !== 0) {
            throw new RuntimeException('Este cliente lleva recargo de equivalencia o un IVA especial (exento, intracomunitario…). Para no descuadrar importes, hazlo en FactuSOL.');
        }

        return ['cli' => $cli, 'fop' => (string) ($cli['FPACLI'] ?? ''), 'age' => (int) ($cli['AGECLI'] ?? 0), 'req' => 0, 'tiv' => 0];
    }
}
