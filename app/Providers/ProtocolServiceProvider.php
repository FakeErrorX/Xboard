<?php

namespace App\Providers;

use App\Support\ProtocolManager;
use Illuminate\Support\ServiceProvider;

class ProtocolServiceProvider extends ServiceProvider
{
  /**
   * Register services
   *
   * @return void
   */
  public function register()
  {
    $this->app->scoped('protocols.manager', function ($app) {
      return new ProtocolManager($app);
    });

    $this->app->scoped('protocols.flags', function ($app) {
      return $app->make('protocols.manager')->getAllFlags();
    });
  }

  /**
   * Boot services
   *
   * @return void
   */
  public function boot()
  {
    // Preload and cache protocol classes at startup
    $this->app->make('protocols.manager')->registerAllProtocols();

  }

  /**
   * Provided services
   *
   * @return array
   */
  public function provides()
  {
    return [
      'protocols.manager',
      'protocols.flags',
    ];
  }
}