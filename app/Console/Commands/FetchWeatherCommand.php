<?php

namespace App\Console\Commands;

use App\Jobs\FetchWeatherDataJob;
use App\Services\OpenWeatherService;
use Illuminate\Console\Command;

class FetchWeatherCommand extends Command
{
    protected $signature = 'weather:fetch {--sync : Chạy đồng bộ thay vì queue}';
    protected $description = 'Fetch dữ liệu thời tiết từ OpenWeatherMap cho tất cả quận Đà Nẵng';

    public function handle(OpenWeatherService $weather): void
    {
        if ($this->option('sync')) {
            $this->info('Fetching weather data synchronously...');
            (new FetchWeatherDataJob)->handle($weather);
            $this->info('✅ Done!');
        } else {
            FetchWeatherDataJob::dispatch();
            $this->info('✅ Job dispatched to queue');
        }
    }
}
