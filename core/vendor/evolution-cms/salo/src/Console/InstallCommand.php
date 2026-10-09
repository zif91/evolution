<?php

namespace EvolutionCMS\Salo\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'salo:install {--with= : The services that should be included in the installation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Laravel Salo\'s default Docker Compose file';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->option('with')) {
            $services = $this->option('with') == 'none' ? [] : explode(',', $this->option('with'));
        } elseif ($this->option('no-interaction')) {
            $services = ['mysql', 'redis', 'selenium', 'mailhog'];
        } else {
            $services = $this->gatherServicesWithSymfonyMenu();
        }

        $this->buildDockerCompose($services);
        $this->replaceEnvVariables($services);

        $this->info('Salo scaffolding installed successfully.');
    }

    /**
     * Gather the desired Salo services using a Symfony menu.
     *
     * @return array
     */
    protected function gatherServicesWithSymfonyMenu()
    {
        return $this->choice('Which services would you like to install?', [
            'mysql',
            'mariadb',
            'redis',
            'memcached',
            'meilisearch',
            'minio',
            'mailhog',
            'selenium',
        ], 0, null, true);
    }

    /**
     * Build the Docker Compose file.
     *
     * @param array $services
     * @return void
     */
    protected function buildDockerCompose(array $services)
    {
        $depends = collect($services)
            ->filter(function ($service) {
                return in_array($service, ['mysql', 'pgsql', 'mariadb', 'redis', 'meilisearch', 'minio', 'selenium']);
            })->map(function ($service) {
                return "            - {$service}";
            })->whenNotEmpty(function ($collection) {
                return $collection->prepend('depends_on:');
            })->implode("\n");

        $stubs = rtrim(collect($services)->map(function ($service) {
            return file_get_contents(__DIR__ . "/../../stubs/{$service}.stub");
        })->implode(''));

        $volumes = collect($services)
            ->filter(function ($service) {
                return in_array($service, ['mysql', 'pgsql', 'mariadb', 'redis', 'meilisearch', 'minio']);
            })->map(function ($service) {
                return "    salo{$service}:\n        driver: local";
            })->whenNotEmpty(function ($collection) {
                return $collection->prepend('volumes:');
            })->implode("\n");

        $dockerCompose = file_get_contents(__DIR__ . '/../../stubs/docker-compose.stub');

        $dockerCompose = str_replace('{{depends}}', empty($depends) ? '' : '        ' . $depends, $dockerCompose);
        $dockerCompose = str_replace('{{services}}', $stubs, $dockerCompose);
        $dockerCompose = str_replace('{{volumes}}', $volumes, $dockerCompose);

        // Remove empty lines...
        $dockerCompose = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $dockerCompose);

        file_put_contents($this->laravel->publicPath('docker-compose.yml'), $dockerCompose);
    }

    /**
     * Replace the Host environment variables in the app's .env file.
     *
     * @param array $services
     * @return void
     */
    protected function replaceEnvVariables(array $services)
    {
        if (file_exists(evo()->basePath('custom/.env'))) {
            $environment = file_get_contents(evo()->basePath('custom/.env'));
        } else {
            $environment = file_get_contents(evo()->basePath('custom/.env.docker.example'));
        }

        $values = [];
        foreach (['mysql', 'mariadb', 'pgsql'] as $service) {
            if (in_array($service, $services)) {
                $values += [
                    'DB_CONNECTION' => $service === 'pgsql' ? 'pgsql' : 'mysql',
                    'DB_HOST' => $service,
                    'DB_PORT' => $service === 'pgsql' ? '5432' : '3306',
                    'DB_DATABASE' => 'evo',
                    'DB_USERNAME' => 'salo',
                    'DB_PASSWORD' => 'password',
                ];
                break;
            }
        }
        foreach (['redis' => 'REDIS_HOST', 'memcached' => 'MEMCACHED_HOST'] as $service => $key) {
            if (in_array($service, $services)) {
                $values[$key] = $service;
            }
        }
        if (in_array('meilisearch', $services)) {
            $values += ['SCOUT_DRIVER' => 'meilisearch', 'MEILISEARCH_HOST' => 'http://meilisearch:7700'];
        }
        foreach ($values as $key => $value) {
            $line = $key . '=' . $value;
            $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
            $environment = preg_match($pattern, $environment)
                ? preg_replace($pattern, $line, $environment)
                : rtrim($environment) . "\n" . $line . "\n";
        }

        // Evolution loads core/custom/.env; Compose interpolates the public .env.
        file_put_contents(evo()->basePath('custom/.env'), $environment);
        file_put_contents(evo()->publicPath('.env'), $environment);
    }
}
