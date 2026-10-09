<?php

namespace EvolutionCMS\Salo\Console;

use Illuminate\Console\Command;

class PublishCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'salo:publish';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish the Laravel Salo Docker files';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->call('vendor:publish', ['--tag' => 'salo']);

        $composePath = $this->laravel->publicPath('docker-compose.yml');
        if (!is_file($composePath)) {
            $this->warn('Run salo:install before publishing the Compose configuration.');
            return;
        }
        file_put_contents($composePath, str_replace(
            'core/vendor/evolution-cms/salo/runtimes/8.3',
            'core/docker/8.3',
            file_get_contents($composePath)
        ));
    }
}
