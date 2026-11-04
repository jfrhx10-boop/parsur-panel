<?php

namespace App\Console\Commands;

use App\Services\FactusolClient;
use App\Services\FactusolSync;
use Illuminate\Console\Command;

class SyncFactusol extends Command
{
    protected $signature = 'factusol:sync {--seconds=50}';

    protected $description = 'Trae clientes de FactuSOL por tandas';

    public function handle(): int
    {
        if (! FactusolClient::configured()) {
            $this->warn('FactuSOL no está conectado.');

            return self::SUCCESS;
        }
        $r = (new FactusolSync)->run((int) $this->option('seconds'));
        $this->info("Consultados {$r['done']}, clientes {$r['found']}, siguiente código {$r['cursor']}".($r['finished'] ? ' (terminado)' : ''));

        return self::SUCCESS;
    }
}
