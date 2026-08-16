<?php

namespace App\Console\Commands;

use App\Services\ExchangeRateService;
use Illuminate\Console\Command;

class RefreshExchangeRate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exchange-rate:refresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ambil kurs USD->IDR terbaru dari open.er-api.com dan simpan ke cache';

    public function handle(ExchangeRateService $service): int
    {
        $rate = $service->refresh();

        $this->info("Kurs USD->IDR diperbarui: 1 USD = Rp {$rate}");

        return self::SUCCESS;
    }
}
