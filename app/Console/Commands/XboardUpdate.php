<?php

namespace App\Console\Commands;

use App\Services\ThemeService;
use App\Services\UpdateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use App\Services\Plugin\PluginManager;
use App\Models\Plugin;
use Illuminate\Support\Str;
use App\Console\Commands\XboardInstall;

class XboardUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xboard:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'xboard update';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Importing database, please wait...');
        Artisan::call("migrate");
        $this->info(Artisan::output());
        $this->info('Checking built-in plugin files...');
        XboardInstall::restoreProtectedPlugins($this);
        $this->info('Checking and installing default plugins...');
        PluginManager::installDefaultPlugins();
        $this->info('Default plugin check completed');
        // Artisan::call('reset:traffic', ['--fix-null' => true]);
        $this->info('Recalculating all users reset time...');
        Artisan::call('reset:traffic', ['--force' => true]);
        $updateService = new UpdateService();
        $updateService->updateVersionCache();
        $themeService = app(ThemeService::class);
        $themeService->refreshCurrentTheme();
        Artisan::call('horizon:terminate');
        $this->info('Update completed, queue service has been restarted, no action needed from you.');
    }
}
