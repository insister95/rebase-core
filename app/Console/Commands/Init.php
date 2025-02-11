<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;

class Init extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Application Initialization: Configuring APP, LOG, DB, Redis, Queue, Cache, Running Migrations, Seeders...';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // 初始化lock文件，如果存在则跳过
        $lockFile = storage_path('app/init.lock');
        if (file_exists($lockFile)) {
            $this->info("If you want to reinitialize, please delete {$lockFile}");
            return;
        }

        $this->info("Configuring APP...(Automatically set the APP_KEY)");
        $this->configureApp();

        $this->info("Configuring LOG...(Automatically set the LOG_LEVEL by APP_ENV)");
        $this->configureLog();

        $this->info("Configuring DB...(Currently only supports MySQL, please adjust other databases by yourself)");
        $this->configureDB();

        $this->info("Configuring Redis...");
        $this->configureRedis();

        $this->info("Configuring QUEUE...(Currently only supports Redis, please adjust other connections by yourself)");
        $this->configureQueue();

        $this->info("Configuring CACHE...(Currently only supports Redis, please adjust other drivers by yourself)");
        $this->configureCache();

        $this->info('Running Migrations...');
        Artisan::call('migrate', ['--force' => true]);
        $this->info('Running Migrations successful!');

        $this->info('Running Seeders...');
        Artisan::call('db:seed', ['--force' => true]);
        $this->info('Running Seeders successful!');

        // 创建初始化 lock 文件
        file_put_contents($lockFile, 'Initialized at ' . date('Y-m-d H:i:s'));
        $this->info('Initialization lock file created successfully!');

        $this->info('Application Initialization successfully!');
    }

    protected function configureApp(): void
    {
        $appName = $this->ask('Enter the APP_NAME:', 'rebase');
        $appEnv = $this->choice('Enter the APP_ENV:', get_envs(), 0);
        $appDebug = $this->choice('Enter the APP_DEBUG:', ['true', 'false'], 0);
        $appUrl = $this->ask('Enter the APP_URL:', 'http://localhost');
        $appLocale = $this->choice('Enter the APP_LOCALE(Automatically set the corresponding time zone according to the selected locale):',
            get_languages(), 0);
        $appFallBackLocale = $this->choice('Enter the APP_FALLBACK_LOCALE:',
            get_languages(), 0);
        $appFakerLocale = $this->choice('Enter the APP_FAKER_LOCALE:',
            get_languages(), 0);
        // 生成APP_KEY
        Artisan::call('key:generate');
        $this->updateEnv([
            'APP_NAME' => $appName,
            'APP_ENV' => $appEnv,
            'APP_DEBUG' => $appDebug,
            'APP_URL' => $appUrl,
            'APP_LOCALE' => $appLocale, // 默认语言环境
            'APP_FALLBACK_LOCALE' => $appFallBackLocale, // 翻译失败回退语言环境
            'APP_FAKER_LOCALE' => $appFakerLocale, // Faker库生成模拟数据语言环境
            'APP_TIMEZONE' => get_timezone_with_language($appLocale),
        ]);
        // 刷新配置缓存
        Artisan::call('config:cache');
        $this->info('Configuring APP successful!');
    }

    protected function configureLog(): void
    {
        $sysLogChannels = $sysLogStacks = ['single', 'daily', 'slack', 'syslog', 'errorlog', 'custom', 'stack'];
        $logChannel = $this->choice('Enter the LOG_CHANNEL:', $sysLogChannels, 6);
        $logStack = $this->choice('Enter the LOG_STACK:', $sysLogStacks, 1);
        $logLevel = is_prod() ? 'error' : 'debug';
        $logDailyDays = $this->choice('Enter the LOG_DAILY_DAYS:', [7, 14, 30], 1);
        $this->updateEnv([
            'LOG_CHANNEL' => $logChannel,
            'LOG_STACK' => $logStack,
            'LOG_LEVEL' => $logLevel,
            'LOG_DAILY_DAYS' => $logDailyDays
        ]);
        // 刷新配置缓存
        Artisan::call('config:cache');
        $this->info('Configuring LOG successful!');
    }

    protected function configureDB(): void
    {
        $dbConnection = $this->choice('Enter the DB_CONNECTION:', ['mysql'], 0);
        $dbHost = $this->ask('Enter the DB_HOST:', '127.0.0.1');
        $dbPort = $this->ask('Enter the DB_PORT:', '3306');
        $dbDatabase = $this->ask('Enter the DB_DATABASE:', 'rebase');
        $dbUsername = $this->ask('Enter the DB_USERNAME:', 'root');
        $dbPassword = $this->secret('Enter the DB_PASSWORD:');
        $dbCharset = $this->ask('Enter the DB_CHARSET:', 'utf8mb4');
        $dbCollation = $this->ask('Enter the DB_COLLATION:', 'utf8mb4_unicode_ci');
        $dbPrefix = $this->ask('Enter the DB_PREFIX:', '');

        // 连接到默认数据库（如 mysql）
        config(['database.default' => $dbConnection]);
        config([
            "database.connections.{$dbConnection}" => [
                'driver' => $dbConnection,
                'host' => $dbHost,
                'port' => $dbPort,
                'database' => null,
                'username' => $dbUsername,
                'password' => $dbPassword,
                'charset' => $dbCharset,
                'collation' => $dbCollation,
                'prefix' => $dbPrefix,
            ],
        ]);
        $this->info('Connecting to MySQL server to create database...');
        DB::purge('mysql');
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $this->error('Failed to connect to MySQL server: ' . $e->getMessage());
            return;
        }

        // 创建数据库
        $this->info("Creating database: {$dbDatabase}...");
        try {
            DB::statement("CREATE DATABASE IF NOT EXISTS {$dbDatabase} CHARACTER SET {$dbCharset} COLLATE {$dbCollation};");
            $this->info("Database '{$dbDatabase}' created successfully!");
        } catch (\Exception $e) {
            $this->error('Failed to create database: ' . $e->getMessage());
            return;
        }

        // 测试新数据库连接
        $this->info('Testing connection to the new database...');
        config(['database.connections.mysql.database' => $dbDatabase]);
        DB::purge('mysql');
        try {
            DB::connection()->getPdo();
            $this->info('Database connection successful!');
        } catch (\Exception $e) {
            $this->error('Database connection failed: ' . $e->getMessage());
            return;
        }

        $this->updateEnv([
            'DB_CONNECTION' => $dbConnection,
            'DB_HOST' => $dbHost,
            'DB_PORT' => $dbPort,
            'DB_DATABASE' => $dbDatabase,
            'DB_USERNAME' => $dbUsername,
            'DB_PASSWORD' => $dbPassword,
            'DB_CHARSET' => $dbCharset,
            'DB_COLLATION' => $dbCollation,
            'DB_PREFIX' => $dbPrefix
        ]);
        // 刷新配置缓存
        Artisan::call('config:cache');
        $this->info('Configuring DB successful!');
    }

    protected function configureRedis(): void
    {
        $sysRedisDbs = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];
        $redisClient = $this->choice('Enter the REDIS_CLIENT:', ['predis', 'phpredis'], 0);
        $redisHost = $this->ask('Enter the REDIS_HOST:', '127.0.0.1');
        $redisPassword = $this->secret('Enter the REDIS_PASSWORD:');
        $redisPort = $this->ask('Enter the REDIS_PORT:', '6379');
        $redisDb = $this->choice('Enter the REDIS_DB:', $sysRedisDbs, 0);
        $redisCacheDb = $this->choice('Enter the REDIS_CACHE_DB:', $sysRedisDbs, 1);
        $redisLockDb = $this->choice('Enter the REDIS_LOCK_DB:', $sysRedisDbs, 2);
        $redisQueueDb = $this->choice('Enter the REDIS_QUEUE_DB:', $sysRedisDbs, 3);
        $redisPrefix = $this->ask('Enter the REDIS_PREFIX:', '');
        $redisCacheLockConnection = $this->choice('Enter the REDIS_CACHE_LOCK_CONNECTION:', ['default', 'lock'], 1);
        $redisQueueConnection = $this->choice('Enter the REDIS_QUEUE_CONNECTION:', ['default', 'queue'], 1);

        config(['database.redis.client' => $redisClient]);
        config([
            'database.redis.default' => [
                'host' => $redisHost,
                'password' => $redisPassword,
                'port' => $redisPort,
                'database' => $redisDb
            ],
        ]);
        // 测试 Redis 连接
        $this->info('Testing Redis connection...');
        try {
            $redis = Redis::connection();
            $redis->client()->ping('ping');
            $this->info('Redis connection successful!');
        } catch (\Exception $e) {
            $this->error('Redis connection failed: ' . $e->getMessage());
            return;
        }

        $this->updateEnv([
            'REDIS_CLIENT' => $redisClient,
            'REDIS_HOST' => $redisHost,
            'REDIS_PASSWORD' => $redisPassword,
            'REDIS_PORT' => $redisPort,
            'REDIS_DB' => $redisDb,
            'REDIS_CACHE_DB' => $redisCacheDb,
            'REDIS_LOCK_DB' => $redisLockDb,
            'REDIS_QUEUE_DB' => $redisQueueDb,
            'REDIS_PREFIX' => $redisPrefix,
            'REDIS_CACHE_LOCK_CONNECTION' => $redisCacheLockConnection,
            'REDIS_QUEUE_CONNECTION' => $redisQueueConnection,
        ]);
        // 刷新配置缓存
        Artisan::call('config:cache');
        $this->info('Configuring Redis successful!');
    }

    protected function configureQueue(): void
    {
        $queueConnection = $this->choice('Enter the QUEUE_CONNECTION:', ['redis'], 0);
        $this->updateEnv([
            'QUEUE_CONNECTION' => $queueConnection,
        ]);
        // 刷新配置缓存
        Artisan::call('config:cache');
        $this->info('Configuring QUEUE successful!');
    }

    protected function configureCache(): void
    {
        $cacheStore = $this->choice('Enter the CACHE_STORE:', ['redis'], 0);
        $cachePrefix = $this->ask('Enter the CACHE_PREFIX:', '');
        $this->updateEnv([
            'CACHE_STORE' => $cacheStore,
            'CACHE_PREFIX' => $cachePrefix,
        ]);
        // 刷新配置缓存
        Artisan::call('config:cache');
        $this->info('Configuring CACHE successful!');
    }

    protected function updateEnv($data): void
    {
        $envPath = base_path('.env');
        $envContent = File::get($envPath);

        foreach ($data as $key => $value) {
            $envContent = preg_replace(
                "/^{$key}=.*/m",
                "{$key}={$value}",
                $envContent
            );
        }

        File::put($envPath, $envContent);
    }
}
