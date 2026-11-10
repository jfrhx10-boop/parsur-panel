<?php

namespace App\Services;

use RuntimeException;

/** Da de alta un cliente en FactuSOL (F_CLI) partiendo del último cliente real como plantilla. */
class CustomerCreator
{
    /** @param array{name:string,nif:string,address:string,city:string,postcode:string,province:string,phone:string,email:string} $d */
    public function create(array $d): array
    {
        $name = mb_strtoupper(trim($d['name']));
        $nif = strtoupper(preg_replace('/[\s\-\.]/', '', $d['nif']));
        if ($name === '') {
            throw new RuntimeException('Falta el nombre fiscal.');
        }
        $api = new FactusolClient;
        $year = (int) date('Y');
        if ($nif !== '') {
            $dup = $api->query($year, "SELECT CODCLI, NOFCLI FROM F_CLI WHERE NIFCLI = '".str_replace("'", "''", $nif)."'");
            if ($dup) {
                throw new RuntimeException('Ya existe en FactuSOL: cliente '.$dup[0]['CODCLI'].' ('.$dup[0]['NOFCLI'].').');
            }
        }
        $max = $api->query($year, 'SELECT MAX(CODCLI) AS M FROM F_CLI');
        $last = (int) ($max[0]['M'] ?? 0);
        $t = $last ? ($api->table($year, 'F_CLI', "CODCLI = $last")[0] ?? null) : null;
        if (! $t) {
            throw new RuntimeException('No hay un cliente anterior en FactuSOL que sirva de plantilla.');
        }
        $new = $last + 1;
        $now = now('Europe/Madrid');
        $h = $t;
        $h['CODCLI'] = $new;
        $h['NIFCLI'] = $nif;
        $h['NOFCLI'] = mb_substr($name, 0, 100);
        $h['NOCCLI'] = mb_substr($name, 0, 100);
        $h['DOMCLI'] = mb_substr(mb_strtoupper(trim($d['address'])), 0, 100);
        $h['POBCLI'] = mb_strtoupper(trim($d['city']));
        $h['CPOCLI'] = trim($d['postcode']);
        $h['PROCLI'] = mb_strtoupper(trim($d['province']));
        $h['TELCLI'] = trim($d['phone']);
        $h['EMACLI'] = trim($d['email']);
        $h['FALCLI'] = $now->format('Y-m-d').'T00:00:00';
        $h['FUMCLI'] = $now->format('Y-m-d').'T00:00:00';
        $h['HALCLI'] = '1900-01-01T'.$now->format('H:i:s');
        $h['HUMCLI'] = '1900-01-01T'.$now->format('H:i:s');
        $api->insert($year, 'F_CLI', $h);
        $chk = $api->table($year, 'F_CLI', "CODCLI = $new")[0] ?? null;
        if (! $chk || ($chk['NIFCLI'] ?? '') !== $nif) {
            throw new RuntimeException("Cliente $new escrito, pero la comprobación no cuadra. Revísalo en FactuSOL.");
        }
        Activity::log('cliente_creado', "cliente $new $name");
        try {
            (new FactusolSync)->syncOne($new);
        } catch (\Throwable $e) {
        }

        return ['code' => $new, 'name' => $name];
    }
}
