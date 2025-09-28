<?php

namespace App\Services\Plugin;

use App\Models\Plugin;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PluginManager
{
    protected string $pluginPath;
    protected array $loadedPlugins = [];
    protected bool $pluginsInitialized = false;

    public function __construct()
    {
        $this->pluginPath = base_path('plugins');
    }

    /**
     * Get plugin namespace
     */
    public function getPluginNamespace(string $pluginCode): string
    {
        return 'Plugin\\' . Str::studly($pluginCode);
    }

    /**
     * Get plugin base path
     */
    public function getPluginPath(string $pluginCode): string
    {
        return $this->pluginPath . '/' . Str::studly($pluginCode);
    }

    /**
     * Load plugin class
     */
    protected function loadPlugin(string $pluginCode): ?AbstractPlugin
    {
        if (isset($this->loadedPlugins[$pluginCode])) {
            return $this->loadedPlugins[$pluginCode];
        }

        $pluginClass = $this->getPluginNamespace($pluginCode) . '\\Plugin';

        if (!class_exists($pluginClass)) {
            $pluginFile = $this->getPluginPath($pluginCode) . '/Plugin.php';
            if (!File::exists($pluginFile)) {
                Log::warning("Plugin class file not found: {$pluginFile}");
                Plugin::query()->where('code', $pluginCode)->delete();
                return null;
            }
            require_once $pluginFile;
        }

        if (!class_exists($pluginClass)) {
            Log::error("Plugin class not found: {$pluginClass}");
            return null;
        }

        $plugin = new $pluginClass($pluginCode);
        $this->loadedPlugins[$pluginCode] = $plugin;

        return $plugin;
    }

    /**
     * Register plugin service provider
     */
    protected function registerServiceProvider(string $pluginCode): void
    {
        $providerClass = $this->getPluginNamespace($pluginCode) . '\\Providers\\PluginServiceProvider';

        if (class_exists($providerClass)) {
            app()->register($providerClass);
        }
    }

    /**
     * Load plugin routes
     */
    protected function loadRoutes(string $pluginCode): void
    {
        $routesPath = $this->getPluginPath($pluginCode) . '/routes';
        if (File::exists($routesPath)) {
            $webRouteFile = $routesPath . '/web.php';
            $apiRouteFile = $routesPath . '/api.php';
            if (File::exists($webRouteFile)) {
                Route::middleware('web')
                    ->namespace($this->getPluginNamespace($pluginCode) . '\\Controllers')
                    ->group(function () use ($webRouteFile) {
                        require $webRouteFile;
                    });
            }
            if (File::exists($apiRouteFile)) {
                Route::middleware('api')
                    ->namespace($this->getPluginNamespace($pluginCode) . '\\Controllers')
                    ->group(function () use ($apiRouteFile) {
                        require $apiRouteFile;
                    });
            }
        }
    }

    /**
     * Load plugin views
     */
    protected function loadViews(string $pluginCode): void
    {
        $viewsPath = $this->getPluginPath($pluginCode) . '/resources/views';
        if (File::exists($viewsPath)) {
            View::addNamespace(Str::studly($pluginCode), $viewsPath);
            return;
        }
    }

    /**
     * Register plugin commands
     */
    protected function registerPluginCommands(string $pluginCode, AbstractPlugin $pluginInstance): void
    {
        try {
            // Call plugin command registration method
            $pluginInstance->registerCommands();
        } catch (\Exception $e) {
            Log::error("Failed to register commands for plugin '{$pluginCode}': " . $e->getMessage());
        }
    }

    /**
     * Install plugin
     */
    public function install(string $pluginCode): bool
    {
        $configFile = $this->getPluginPath($pluginCode) . '/config.json';

        if (!File::exists($configFile)) {
            throw new \Exception('Plugin config file not found');
        }

        $config = json_decode(File::get($configFile), true);
        if (!$this->validateConfig($config)) {
            throw new \Exception('Invalid plugin config');
        }

        // Check if plugin is already installed
        if (Plugin::where('code', $pluginCode)->exists()) {
            throw new \Exception('Plugin already installed');
        }

        // Check dependencies
        if (!$this->checkDependencies($config['require'] ?? [])) {
            throw new \Exception('Dependencies not satisfied');
        }

        // Run database migrations
        $this->runMigrations(pluginCode: $pluginCode);

        DB::beginTransaction();
        try {
            // Extract configuration default values
            $defaultValues = $this->extractDefaultConfig($config);

            // Create plugin instance
            $plugin = $this->loadPlugin($pluginCode);

            // Register to database
            Plugin::create([
                'code' => $pluginCode,
                'name' => $config['name'],
                'version' => $config['version'],
                'type' => $config['type'] ?? Plugin::TYPE_FEATURE,
                'is_enabled' => false,
                'config' => json_encode($defaultValues),
                'installed_at' => now(),
            ]);

            // Run plugin installation method
            if (method_exists($plugin, 'install')) {
                $plugin->install();
            }

            // Publish plugin assets
            $this->publishAssets($pluginCode);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            throw $e;
        }
    }

    /**
     * Extract plugin default configuration
     */
    protected function extractDefaultConfig(array $config): array
    {
        $defaultValues = [];
        if (isset($config['config']) && is_array($config['config'])) {
            foreach ($config['config'] as $key => $item) {
                if (is_array($item)) {
                    $defaultValues[$key] = $item['default'] ?? null;
                } else {
                    $defaultValues[$key] = $item;
                }
            }
        }
        return $defaultValues;
    }

    /**
     * Run plugin database migrations
     */
    protected function runMigrations(string $pluginCode): void
    {
        $migrationsPath = $this->getPluginPath($pluginCode) . '/database/migrations';

        if (File::exists($migrationsPath)) {
            Artisan::call('migrate', [
                '--path' => "plugins/" . Str::studly($pluginCode) . "/database/migrations",
                '--force' => true
            ]);
        }
    }

    /**
     * Rollback plugin database migrations
     */
    protected function runMigrationsRollback(string $pluginCode): void
    {
        $migrationsPath = $this->getPluginPath($pluginCode) . '/database/migrations';

        if (File::exists($migrationsPath)) {
            Artisan::call('migrate:rollback', [
                '--path' => "plugins/" . Str::studly($pluginCode) . "/database/migrations",
                '--force' => true
            ]);
        }
    }

    /**
     * Publish plugin assets
     */
    protected function publishAssets(string $pluginCode): void
    {
        $assetsPath = $this->getPluginPath($pluginCode) . '/resources/assets';
        if (File::exists($assetsPath)) {
            $publishPath = public_path('plugins/' . $pluginCode);
            File::ensureDirectoryExists($publishPath);
            File::copyDirectory($assetsPath, $publishPath);
        }
    }

    /**
     * Validate configuration file
     */
    protected function validateConfig(array $config): bool
    {
        $requiredFields = [
            'name',
            'code',
            'version',
            'description',
            'author'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($config[$field]) || empty($config[$field])) {
                return false;
            }
        }

        // Validate plugin code format
        if (!preg_match('/^[a-z0-9_]+$/', $config['code'])) {
            return false;
        }

        // Validate version number format
        if (!preg_match('/^\d+\.\d+\.\d+$/', $config['version'])) {
            return false;
        }

        // Validate plugin type
        if (isset($config['type'])) {
            $validTypes = ['feature', 'payment'];
            if (!in_array($config['type'], $validTypes)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Enable plugin
     */
    public function enable(string $pluginCode): bool
    {
        $plugin = $this->loadPlugin($pluginCode);

        if (!$plugin) {
            Plugin::where('code', $pluginCode)->delete();
            throw new \Exception('Plugin not found: ' . $pluginCode);
        }

        // Get plugin configuration
        $dbPlugin = Plugin::query()
            ->where('code', $pluginCode)
            ->first();

        if ($dbPlugin && !empty($dbPlugin->config)) {
            $plugin->setConfig(json_decode($dbPlugin->config, true));
        }

        // Register service provider
        $this->registerServiceProvider($pluginCode);

        // Load routes
        $this->loadRoutes($pluginCode);

        // Load views
        $this->loadViews($pluginCode);

        // Update database status
        Plugin::query()
            ->where('code', $pluginCode)
            ->update([
                'is_enabled' => true,
                'updated_at' => now(),
            ]);
        // Initialize plugin
        $plugin->boot();

        return true;
    }

    /**
     * Disable plugin
     */
    public function disable(string $pluginCode): bool
    {
        $plugin = $this->loadPlugin($pluginCode);
        if (!$plugin) {
            throw new \Exception('Plugin not found');
        }

        Plugin::query()
            ->where('code', $pluginCode)
            ->update([
                'is_enabled' => false,
                'updated_at' => now(),
            ]);

        $plugin->cleanup();

        return true;
    }

    /**
     * Uninstall plugin
     */
    public function uninstall(string $pluginCode): bool
    {
        $this->disable($pluginCode);
        $this->runMigrationsRollback($pluginCode);
        Plugin::query()->where('code', $pluginCode)->delete();

        return true;
    }

    /**
     * Delete plugin
     *
     * @param string $pluginCode
     * @return bool
     * @throws \Exception
     */
    public function delete(string $pluginCode): bool
    {
        // First uninstall plugin
        if (Plugin::where('code', $pluginCode)->exists()) {
            $this->uninstall($pluginCode);
        }

        $pluginPath = $this->getPluginPath($pluginCode);
        if (!File::exists($pluginPath)) {
            throw new \Exception('Plugin does not exist');
        }

        // Delete plugin directory
        File::deleteDirectory($pluginPath);

        return true;
    }

    /**
     * Check dependencies
     */
    protected function checkDependencies(array $requires): bool
    {
        foreach ($requires as $package => $version) {
            if ($package === 'xboard') {
                // Check xboard version
                // Implement version comparison logic
            }
        }
        return true;
    }

    /**
     * Upgrade plugin
     *
     * @param string $pluginCode
     * @return bool
     * @throws \Exception
     */
    public function update(string $pluginCode): bool
    {
        $dbPlugin = Plugin::where('code', $pluginCode)->first();
        if (!$dbPlugin) {
            throw new \Exception('Plugin not installed: ' . $pluginCode);
        }

        // Get latest version from plugin config file
        $configFile = $this->getPluginPath($pluginCode) . '/config.json';
        if (!File::exists($configFile)) {
            throw new \Exception('Plugin config file not found');
        }

        $config = json_decode(File::get($configFile), true);
        if (!$config || !isset($config['version'])) {
            throw new \Exception('Invalid plugin config or missing version');
        }

        $newVersion = $config['version'];
        $oldVersion = $dbPlugin->version;

        if (version_compare($newVersion, $oldVersion, '<=')) {
            throw new \Exception('Plugin is already up to date');
        }

        $this->disable($pluginCode);
        $this->runMigrations($pluginCode);

        $plugin = $this->loadPlugin($pluginCode);
        if ($plugin) {
            if (!empty($dbPlugin->config)) {
                $plugin->setConfig(json_decode($dbPlugin->config, true));
            }

            $plugin->update($oldVersion, $newVersion);
        }

        $dbPlugin->update([
            'version' => $newVersion,
            'updated_at' => now(),
        ]);

        $this->enable($pluginCode);

        return true;
    }

    /**
     * Upload plugin
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return bool
     * @throws \Exception
     */
    public function upload($file): bool
    {
        $tmpPath = storage_path('tmp/plugins');
        if (!File::exists($tmpPath)) {
            File::makeDirectory($tmpPath, 0755, true);
        }

        $extractPath = $tmpPath . '/' . uniqid();
        $zip = new \ZipArchive();

        if ($zip->open($file->path()) !== true) {
            throw new \Exception('Unable to open plugin package file');
        }

        $zip->extractTo($extractPath);
        $zip->close();

        $configFile = File::glob($extractPath . '/*/config.json');
        if (empty($configFile)) {
            $configFile = File::glob($extractPath . '/config.json');
        }

        if (empty($configFile)) {
            File::deleteDirectory($extractPath);
            throw new \Exception('Plugin package format error: missing configuration file');
        }

        $pluginPath = dirname(reset($configFile));
        $config = json_decode(File::get($pluginPath . '/config.json'), true);

        if (!$this->validateConfig($config)) {
            File::deleteDirectory($extractPath);
            throw new \Exception('Plugin configuration file format error');
        }

        $targetPath = $this->pluginPath . '/' . Str::studly($config['code']);
        if (File::exists($targetPath)) {
            $installedConfigPath = $targetPath . '/config.json';
            if (!File::exists($installedConfigPath)) {
                throw new \Exception('Installed plugin missing configuration file, cannot determine if upgrade is possible');
            }
            $installedConfig = json_decode(File::get($installedConfigPath), true);

            $oldVersion = $installedConfig['version'] ?? null;
            $newVersion = $config['version'] ?? null;
            if (!$oldVersion || !$newVersion) {
                throw new \Exception('Plugin missing version number, cannot determine if upgrade is possible');
            }
            if (version_compare($newVersion, $oldVersion, '<=')) {
                throw new \Exception('Uploaded plugin version is not higher than installed version, cannot upgrade');
            }

            File::deleteDirectory($targetPath);
        }

        File::copyDirectory($pluginPath, $targetPath);
        File::deleteDirectory($pluginPath);
        File::deleteDirectory($extractPath);

        if (Plugin::where('code', $config['code'])->exists()) {
            return $this->update($config['code']);
        }

        return true;
    }

    /**
     * Initializes all enabled plugins from the database.
     * This method ensures that plugins are loaded, and their routes, views,
     * and service providers are registered only once per request cycle.
     */
    public function initializeEnabledPlugins(): void
    {
        if ($this->pluginsInitialized) {
            return;
        }

        $enabledPlugins = Plugin::where('is_enabled', true)->get();

        foreach ($enabledPlugins as $dbPlugin) {
            try {
                $pluginCode = $dbPlugin->code;

                $pluginInstance = $this->loadPlugin($pluginCode);
                if (!$pluginInstance) {
                    continue;
                }

                if (!empty($dbPlugin->config)) {
                    $pluginInstance->setConfig(json_decode($dbPlugin->config, true));
                }

                $this->registerServiceProvider($pluginCode);
                $this->loadRoutes($pluginCode);
                $this->loadViews($pluginCode);
                $this->registerPluginCommands($pluginCode, $pluginInstance);

                $pluginInstance->boot();

            } catch (\Exception $e) {
                Log::error("Failed to initialize plugin '{$dbPlugin->code}': " . $e->getMessage());
            }
        }

        $this->pluginsInitialized = true;
    }

    /**
     * Register scheduled tasks for all enabled plugins.
     * Called from Console Kernel. Only loads main plugin class and config for scheduling.
     * Avoids full HTTP/plugin boot overhead.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     */
    public function registerPluginSchedules(Schedule $schedule): void
    {
        Plugin::where('is_enabled', true)
            ->get()
            ->each(function ($dbPlugin) use ($schedule) {
                try {
                    $pluginInstance = $this->loadPlugin($dbPlugin->code);
                    if (!$pluginInstance) {
                        return;
                    }
                    if (!empty($dbPlugin->config)) {
                        $pluginInstance->setConfig(json_decode($dbPlugin->config, true));
                    }
                    $pluginInstance->schedule($schedule);

                } catch (\Exception $e) {
                    Log::error("Failed to register schedule for plugin '{$dbPlugin->code}': " . $e->getMessage());
                }
            });
    }

    /**
     * Get all enabled plugin instances.
     *
     * This method ensures that all enabled plugins are initialized and then returns them.
     * It's the central point for accessing active plugins.
     *
     * @return array<AbstractPlugin>
     */
    public function getEnabledPlugins(): array
    {
        $this->initializeEnabledPlugins();

        $enabledPluginCodes = Plugin::where('is_enabled', true)
            ->pluck('code')
            ->all();

        return array_intersect_key($this->loadedPlugins, array_flip($enabledPluginCodes));
    }

    /**
     * Get enabled plugins by type
     */
    public function getEnabledPluginsByType(string $type): array
    {
        $this->initializeEnabledPlugins();

        $enabledPluginCodes = Plugin::where('is_enabled', true)
            ->byType($type)
            ->pluck('code')
            ->all();

        return array_intersect_key($this->loadedPlugins, array_flip($enabledPluginCodes));
    }

    /**
     * Get enabled payment plugins
     */
    public function getEnabledPaymentPlugins(): array
    {
        return $this->getEnabledPluginsByType('payment');
    }

    /**
     * install default plugins
     */
    public static function installDefaultPlugins(): void
    {
        foreach (Plugin::PROTECTED_PLUGINS as $pluginCode) {
            if (!Plugin::where('code', $pluginCode)->exists()) {
                $pluginManager = app(self::class);
                $pluginManager->install($pluginCode);
                $pluginManager->enable($pluginCode);
                Log::info("Installed and enabled default plugin: {$pluginCode}");
            }
        }
    }
}