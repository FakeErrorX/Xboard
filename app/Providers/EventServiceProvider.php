<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Event listener mappings
     * @var array<string, array<int, class-string>>
     */
    protected $listen = [
    ];

    /**
     * Register any events
     * @return void
     */
    public function boot()
    {
        parent::boot();

        User::observe(UserObserver::class);
    }
}
