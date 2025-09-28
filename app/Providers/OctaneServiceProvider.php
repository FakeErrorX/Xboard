<?php

namespace App\Providers;

use App\Services\Plugin\HookManager;
use App\Services\UpdateService;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Facades\Octane;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Laravel\Octane\Events\WorkerStarting;

class OctaneServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }
        if ($this->app->bound('octane')) {
            $this->app['events']->listen(WorkerStarting::class, function () {
                app(UpdateService::class)->updateVersionCache();
                HookManager::reset();
            });
        }
        // Execute scheduler check every 30 seconds
        Octane::tick('scheduler', function () {
            $lock = Cache::lock('scheduler-lock', 30);

            if ($lock->get()) {
                try {
                    Artisan::call('schedule:run');
                } finally {
                    $lock->release();
                }
            }
        })->seconds(30);
    }
}