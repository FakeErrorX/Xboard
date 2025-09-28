<?php

namespace App\Console\Commands;

use App\Services\Plugin\PluginManager;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use App\Models\User;
use App\Utils\Helper;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use App\Models\Plugin;
use Illuminate\Support\Str;

class XboardInstall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xboard:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'xboard initialization installation';

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
        try {
            $isDocker = false;
            $enableSqlite = getenv('ENABLE_SQLITE', false);
            $enableRedis = getenv('ENABLE_REDIS', false);
            $adminAccount = getenv('ADMIN_ACCOUNT', false);
            $this->info("__    __ ____                      _  ");
            $this->info("\ \  / /| __ )  ___   __ _ _ __ __| | ");
            $this->info(" \ \/ / | __ \ / _ \ / _` | '__/ _` | ");
            $this->info(" / /\ \ | |_) | (_) | (_| | | | (_| | ");
            $this->info("/_/  \_\|____/ \___/ \__,_|_|  \__,_| ");
            if (
                (File::exists(base_path() . '/.env') && $this->getEnvValue('INSTALLED'))
            ) {
                $securePath = admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key'))));
                $this->info("Visit http(s)://your-site/{$securePath} to enter the admin panel, you can modify your password in the user center.");
                $this->warn("To reinstall, please clear the contents of the .env file in the directory");
                $this->warn("Quick command to clear .env:");
                note('rm .env && touch .env');
                return;
            }
            if (is_dir(base_path() . '/.env')) {
                $this->error('😔: Installation failed, please check .env file');
                return;
            }
            // Select database type
            $dbType = $enableSqlite ? 'sqlite' : select(
                label: 'Please select database type',
                options: [
                    'sqlite' => 'SQLite (no additional installation required)',
                    'mysql' => 'MySQL',
                    'postgresql' => 'PostgreSQL'
                ],
                default: 'sqlite'
            );

            // Use match expression to configure database
            $envConfig = match ($dbType) {
                'sqlite' => $this->configureSqlite(),
                'mysql' => $this->configureMysql(),
                'postgresql' => $this->configurePostgresql(),
                default => throw new \InvalidArgumentException("Unsupported database type: {$dbType}")
            };

            if (is_null($envConfig)) {
                return; // User chose to exit installation
            }
            $envConfig['APP_KEY'] = 'base64:' . base64_encode(Encrypter::generateKey('AES-256-CBC'));
            $isReidsValid = false;
            while (!$isReidsValid) {
                $envConfig['REDIS_HOST'] = text(label: 'Please enter Redis address', default: '127.0.0.1', required: true);
                $envConfig['REDIS_PORT'] = text(label: 'Please enter Redis port', default: '6379', required: true);
                $envConfig['REDIS_PASSWORD'] = text(label: 'Please enter redis password (default: null)', default: '');
                $redisConfig = [
                    'client' => 'phpredis',
                    'default' => [
                        'host' => $envConfig['REDIS_HOST'],
                        'password' => $envConfig['REDIS_PASSWORD'],
                        'port' => $envConfig['REDIS_PORT'],
                        'database' => 0,
                    ],
                ];
                try {
                    $redis = new \Illuminate\Redis\RedisManager(app(), 'phpredis', $redisConfig);
                    $redis->ping();
                    $isReidsValid = true;
                } catch (\Exception $e) {
                    // Connection failed, output error message
                    $this->error("Redis connection failed: " . $e->getMessage());
                    $this->info("Please re-enter REDIS configuration");
                    $enableRedis = false;
                    sleep(1);
                }
            }

            if (!copy(base_path() . '/.env.example', base_path() . '/.env')) {
                abort(500, 'Failed to copy environment file, please check directory permissions');
            }
            ;
            $email = !empty($adminAccount) ? $adminAccount : text(
                label: 'Please enter admin account',
                default: 'admin@demo.com',
                required: true,
                validate: fn(string $email): ?string => match (true) {
                    !filter_var($email, FILTER_VALIDATE_EMAIL) => 'Please enter a valid email address.',
                    default => null,
                }
            );
            $password = Helper::guid(false);
            $this->saveToEnv($envConfig);

            $this->call('config:cache');
            Artisan::call('cache:clear');
            $this->info('Importing database, please wait...');
            Artisan::call("migrate", ['--force' => true]);
            $this->info(Artisan::output());
            $this->info('Database import completed');
            $this->info('Starting admin account registration');
            if (!self::registerAdmin($email, $password)) {
                abort(500, 'Admin account registration failed, please try again');
            }
            if (function_exists('exec')) {
                self::restoreProtectedPlugins($this);
            }
            $this->info('Installing default plugins...');
            PluginManager::installDefaultPlugins();
            $this->info('Default plugin installation completed');

            $this->info('🎉: Everything is ready');
            $this->info("Admin email: {$email}");
            $this->info("Admin password: {$password}");

            $defaultSecurePath = hash('crc32b', config('app.key'));
            $this->info("Visit http(s)://your-site/{$defaultSecurePath} to enter the admin panel, you can modify your password in the user center.");
            $envConfig['INSTALLED'] = true;
            $this->saveToEnv($envConfig);
        } catch (\Exception $e) {
            $this->error($e);
        }
    }

    public static function registerAdmin($email, $password)
    {
        $user = new User();
        $user->email = $email;
        if (strlen($password) < 8) {
            abort(500, 'Admin password must be at least 8 characters long');
        }
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->uuid = Helper::guid(true);
        $user->token = Helper::guid();
        $user->is_admin = 1;
        return $user->save();
    }

    private function set_env_var($key, $value)
    {
        $value = !strpos($value, ' ') ? $value : '"' . $value . '"';
        $key = strtoupper($key);

        $envPath = app()->environmentFilePath();
        $contents = file_get_contents($envPath);

        if (preg_match("/^{$key}=[^\r\n]*/m", $contents, $matches)) {
            $contents = str_replace($matches[0], "{$key}={$value}", $contents);
        } else {
            $contents .= "\n{$key}={$value}\n";
        }

        return file_put_contents($envPath, $contents) !== false;
    }

    private function saveToEnv($data = [])
    {
        foreach ($data as $key => $value) {
            self::set_env_var($key, $value);
        }
        return true;
    }

    function getEnvValue($key, $default = null)
    {
        $dotenv = \Dotenv\Dotenv::createImmutable(base_path());
        $dotenv->load();

        return Env::get($key, $default);
    }

    /**
     * Configure SQLite database
     *
     * @return array|null
     */
    private function configureSqlite(): ?array
    {
        $sqliteFile = 'database/database.sqlite';
        if (!file_exists(base_path($sqliteFile))) {
            // Create empty file
            if (!touch(base_path($sqliteFile))) {
                $this->info("SQLite created successfully: $sqliteFile");
            }
        }

        $envConfig = [
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $sqliteFile,
            'DB_HOST' => '',
            'DB_USERNAME' => '',
            'DB_PASSWORD' => '',
        ];

        try {
            Config::set("database.default", 'sqlite');
            Config::set("database.connections.sqlite.database", base_path($envConfig['DB_DATABASE']));
            DB::purge('sqlite');
            DB::connection('sqlite')->getPdo();

            if (!blank(DB::connection('sqlite')->getPdo()->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(\PDO::FETCH_COLUMN))) {
                if (confirm(label: 'Data already exists in the database. Do you want to clear the database to install new data?', default: false, yes: 'Clear', no: 'Exit installation')) {
                    $this->info('Clearing database, please wait');
                    $this->call('db:wipe', ['--force' => true]);
                    $this->info('Database cleared successfully');
                } else {
                    return null;
                }
            }
        } catch (\Exception $e) {
            $this->error("SQLite database connection failed: " . $e->getMessage());
            return null;
        }

        return $envConfig;
    }

    /**
     * Configure MySQL database
     *
     * @return array
     */
    private function configureMysql(): array
    {
        while (true) {
            $envConfig = [
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => text(label: "Please enter MySQL database host", default: '127.0.0.1', required: true),
                'DB_PORT' => text(label: 'Please enter MySQL database port', default: '3306', required: true),
                'DB_DATABASE' => text(label: 'Please enter MySQL database name', default: 'proxybd', required: true),
                'DB_USERNAME' => text(label: 'Please enter MySQL database username', default: 'proxybd', required: true),
                'DB_PASSWORD' => text(label: 'Please enter MySQL database password', required: false),
            ];

            try {
                Config::set("database.default", 'mysql');
                Config::set("database.connections.mysql.host", $envConfig['DB_HOST']);
                Config::set("database.connections.mysql.port", $envConfig['DB_PORT']);
                Config::set("database.connections.mysql.database", $envConfig['DB_DATABASE']);
                Config::set("database.connections.mysql.username", $envConfig['DB_USERNAME']);
                Config::set("database.connections.mysql.password", $envConfig['DB_PASSWORD']);
                DB::purge('mysql');
                DB::connection('mysql')->getPdo();

                if (!blank(DB::connection('mysql')->select('SHOW TABLES'))) {
                    if (confirm(label: 'Database already contains data. Do you want to clear the database for installing new data?', default: false, yes: 'Clear', no: 'Don\'t clear')) {
                        $this->info('Clearing database, please wait');
                        $this->call('db:wipe', ['--force' => true]);
                        $this->info('Database cleared successfully');
                        return $envConfig;
                    } else {
                        continue; // Re-enter configuration
                    }
                }

                return $envConfig;
            } catch (\Exception $e) {
                $this->error("MySQL database connection failed: " . $e->getMessage());
                $this->info("Please re-enter MySQL database configuration");
            }
        }
    }

    /**
     * Configure PostgreSQL database
     *
     * @return array
     */
    private function configurePostgresql(): array
    {
        while (true) {
            $envConfig = [
                'DB_CONNECTION' => 'pgsql',
                'DB_HOST' => text(label: "Please enter PostgreSQL database host", default: '127.0.0.1', required: true),
                'DB_PORT' => text(label: 'Please enter PostgreSQL database port', default: '5432', required: true),
                'DB_DATABASE' => text(label: 'Please enter PostgreSQL database name', default: 'xboard', required: true),
                'DB_USERNAME' => text(label: 'Please enter PostgreSQL database username', default: 'postgres', required: true),
                'DB_PASSWORD' => text(label: 'Please enter PostgreSQL database password', required: false),
            ];

            try {
                Config::set("database.default", 'pgsql');
                Config::set("database.connections.pgsql.host", $envConfig['DB_HOST']);
                Config::set("database.connections.pgsql.port", $envConfig['DB_PORT']);
                Config::set("database.connections.pgsql.database", $envConfig['DB_DATABASE']);
                Config::set("database.connections.pgsql.username", $envConfig['DB_USERNAME']);
                Config::set("database.connections.pgsql.password", $envConfig['DB_PASSWORD']);
                DB::purge('pgsql');
                DB::connection('pgsql')->getPdo();

                // Check if PostgreSQL database has tables
                $tables = DB::connection('pgsql')->select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
                if (!blank($tables)) {
                    if (confirm(label: 'Database already contains data. Do you want to clear the database for installing new data?', default: false, yes: 'Clear', no: 'Don\'t clear')) {
                        $this->info('Clearing database, please wait');
                        $this->call('db:wipe', ['--force' => true]);
                        $this->info('Database cleared successfully');
                        return $envConfig;
                    } else {
                        continue; // Re-enter configuration
                    }
                }

                return $envConfig;
            } catch (\Exception $e) {
                $this->error("PostgreSQL database connection failed: " . $e->getMessage());
                $this->info("Please re-enter PostgreSQL database configuration");
            }
        }
    }

    /**
     * Restore built-in protected plugins (can be called during installation and update)
     */
    public static function restoreProtectedPlugins(Command $console = null)
    {
        exec("git config core.filemode false", $output, $returnVar);
        $cmd = "git status --porcelain plugins/ 2>/dev/null";
        exec($cmd, $output, $returnVar);
        if (!empty($output)) {
            $hasNonNewFiles = false;
            foreach ($output as $line) {
                $status = trim(substr($line, 0, 2));
                if ($status !== 'A') {
                    $hasNonNewFiles = true;
                    break;
                }
            }
            if ($hasNonNewFiles) {
                if ($console)
                    $console->info("Changes detected in plugins directory, restoring...");

                foreach ($output as $line) {
                    $status = trim(substr($line, 0, 2));
                    $filePath = trim(substr($line, 3));

                    if (strpos($filePath, 'plugins/') === 0 && $status !== 'A') {
                        $relativePath = substr($filePath, 8);
                        if ($console) {
                            $action = match ($status) {
                                'M' => 'Modified',
                                'D' => 'Deleted',
                                'R' => 'Renamed',
                                'C' => 'Copied',
                                default => 'Changed'
                            };
                            $console->info("Restoring plugin file [{$relativePath}] ({$action})");
                        }

                        $cmd = "git checkout HEAD -- {$filePath}";
                        exec($cmd, $gitOutput, $gitReturnVar);

                        if ($gitReturnVar === 0) {
                            if ($console)
                                $console->info("Plugin file [{$relativePath}] has been restored.");
                        } else {
                            if ($console)
                                $console->error("Plugin file [{$relativePath}] restoration failed.");
                        }
                    }
                }
            } else {
                if ($console)
                    $console->info("plugins directory status is normal, no restoration needed.");
            }
        } else {
            if ($console)
                $console->info("plugins directory status is normal, no restoration needed.");
        }
    }
}
