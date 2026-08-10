<?php

namespace App\Console\Commands;

use App\Services\OmieService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FetchOmiePrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'omie:fetch {date? : Fecha en formato YYYY-MM-DD (opcional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Obtiene y actualiza los precios del mercado diario de energía (OMIE_MD) desde ESIOS / OMIE';

    /**
     * Execute the console command.
     */
    public function handle(OmieService $omieService): int
    {
        $inputDate = $this->argument('date');

        if ($inputDate) {
            $datesToSync = [$inputDate];
        } else {
            // Default: Sync today and tomorrow (OMIE publishes tomorrow's prices around 13:30 CET)
            $datesToSync = [
                Carbon::today()->toDateString(),
                Carbon::tomorrow()->toDateString()
            ];
        }

        $this->info("Iniciando sincronización de precios OMIE_MD...");

        foreach ($datesToSync as $dateStr) {
            $this->output->write("Sincronizando fecha {$dateStr}... ");
            try {
                $result = $omieService->fetchAndSavePrices($dateStr);
                $this->info("¡Éxito! [Fuente: {$result['source']}]");
            } catch (\Throwable $e) {
                $this->error("Error: " . $e->getMessage());
            }
        }

        $this->info("Sincronización completada correctamente.");
        return Command::SUCCESS;
    }
}
