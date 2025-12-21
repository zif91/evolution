<?php

namespace EvolutionCMS\AiAssistant\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai-assistant:install {--force : Overwrite existing plugin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install AI Assistant for Evolution CMS';

    /**
     * Package path
     */
    protected string $packagePath;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->packagePath = dirname(__DIR__, 2);
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('');
        $this->info('╔══════════════════════════════════════════════╗');
        $this->info('║    AI Assistant for Evolution CMS            ║');
        $this->info('║    Installation                              ║');
        $this->info('╚══════════════════════════════════════════════╝');
        $this->info('');

        // Step 1: Run migrations
        $this->info('Step 1: Running migrations...');
        $this->runMigrations();

        // Step 2: Install plugin
        $this->info('Step 2: Installing plugin...');
        $this->installPlugin();

        // Step 3: Publish assets
        $this->info('Step 3: Publishing assets...');
        $this->publishAssets();

        // Step 4: Register provider
        $this->info('Step 4: Registering service provider...');
        $this->registerProvider();

        $this->info('');
        $this->info('═══════════════════════════════════════════════');
        $this->info('✓ AI Assistant installed successfully!');
        $this->info('═══════════════════════════════════════════════');
        $this->info('');
        $this->info('Next steps:');
        $this->info('1. Add your API key to .env:');
        $this->info('   OPENAI_API_KEY=your-api-key');
        $this->info('   or');
        $this->info('   ANTHROPIC_API_KEY=your-api-key');
        $this->info('');
        $this->info('2. (Optional) Configure provider:');
        $this->info('   AI_ASSISTANT_PROVIDER=openai');
        $this->info('');
        $this->info('3. Clear cache and refresh the manager');
        $this->info('');

        return Command::SUCCESS;
    }

    /**
     * Run migrations
     */
    protected function runMigrations(): void
    {
        $migrationsPath = $this->packagePath . '/migrations';

        if (is_dir($migrationsPath)) {
            Artisan::call('migrate', [
                '--path' => 'core/custom/packages/ai-assistant/migrations',
                '--force' => true,
            ]);
            $this->info('   Migrations completed.');
        } else {
            $this->warn('   No migrations found.');
        }
    }

    /**
     * Install Evolution CMS plugin
     */
    protected function installPlugin(): void
    {
        $pluginName = 'AI Assistant';
        $pluginFile = $this->packagePath . '/assets/plugins/ai_assistant.php';

        if (!file_exists($pluginFile)) {
            $this->error('   Plugin file not found: ' . $pluginFile);
            return;
        }

        $pluginCode = file_get_contents($pluginFile);
        $existingPlugin = DB::table('site_plugins')->where('name', $pluginName)->first();

        if ($existingPlugin && !$this->option('force')) {
            // Update existing plugin
            DB::table('site_plugins')
                ->where('id', $existingPlugin->id)
                ->update([
                    'plugincode' => $pluginCode,
                    'editedon' => time(),
                ]);
            $this->info('   Plugin updated.');
        } else {
            // Delete if force option
            if ($existingPlugin && $this->option('force')) {
                DB::table('site_plugin_events')->where('pluginid', $existingPlugin->id)->delete();
                DB::table('site_plugins')->where('id', $existingPlugin->id)->delete();
            }

            // Create new plugin
            $pluginId = DB::table('site_plugins')->insertGetId([
                'name' => $pluginName,
                'description' => 'AI Assistant for Evolution CMS - Shopify Sidekick-like assistant',
                'plugincode' => $pluginCode,
                'disabled' => 0,
                'moduleguid' => '',
                'createdon' => time(),
                'editedon' => time(),
            ]);

            // Bind to events
            $events = ['OnManagerFrameLoader', 'OnManagerTopPrerender'];
            foreach ($events as $eventName) {
                $event = DB::table('system_eventnames')->where('name', $eventName)->first();
                if ($event) {
                    DB::table('site_plugin_events')->insert([
                        'pluginid' => $pluginId,
                        'evtid' => $event->id,
                        'priority' => 0,
                    ]);
                }
            }
            $this->info('   Plugin installed.');
        }
    }

    /**
     * Publish assets to public directory
     */
    protected function publishAssets(): void
    {
        $sourceDir = $this->packagePath . '/public';
        $targetDir = EVO_CORE_PATH . '../assets/ai-assistant';

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // Copy CSS
        $cssSource = $sourceDir . '/css';
        $cssTarget = $targetDir . '/css';
        if (!is_dir($cssTarget)) {
            mkdir($cssTarget, 0755, true);
        }
        foreach (glob($cssSource . '/*.css') as $file) {
            copy($file, $cssTarget . '/' . basename($file));
        }

        // Copy JS
        $jsSource = $sourceDir . '/js';
        $jsTarget = $targetDir . '/js';
        if (!is_dir($jsTarget)) {
            mkdir($jsTarget, 0755, true);
        }
        foreach (glob($jsSource . '/*.js') as $file) {
            copy($file, $jsTarget . '/' . basename($file));
        }

        $this->info('   Assets published to: ' . $targetDir);
    }

    /**
     * Register service provider via package:discover
     */
    protected function registerProvider(): void
    {
        // Ensure custom/composer.json has our package
        $composerFile = EVO_CORE_PATH . 'custom/composer.json';
        $composerArray = [
            'name' => 'evolutioncms/custom',
            'require' => [],
            'autoload' => ['psr-4' => []],
            'extra' => ['laravel' => ['providers' => []]]
        ];

        if (file_exists($composerFile)) {
            $composerArray = json_decode(file_get_contents($composerFile), true) ?: $composerArray;
        }

        // Add autoload entry
        $namespace = 'EvolutionCMS\\AiAssistant\\';
        $path = 'packages/ai-assistant/src/';
        if (!isset($composerArray['autoload']['psr-4'][$namespace])) {
            $composerArray['autoload']['psr-4'][$namespace] = $path;
        }

        // Add provider
        $provider = 'EvolutionCMS\\AiAssistant\\AiAssistantServiceProvider';
        if (!isset($composerArray['extra']['laravel']['providers'])) {
            $composerArray['extra']['laravel']['providers'] = [];
        }
        if (!in_array($provider, $composerArray['extra']['laravel']['providers'])) {
            $composerArray['extra']['laravel']['providers'][] = $provider;
        }

        file_put_contents($composerFile, json_encode($composerArray, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        // Run package:discover
        Artisan::call('package:discover');
        $this->info('   Service provider registered.');
    }
}
