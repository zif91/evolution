<?php
namespace EvolutionCMS\Support;

use Illuminate\Contracts\Foundation\MaintenanceMode as MaintenanceModeContract;
use EvolutionCMS\Interfaces\CoreInterface;

/** Laravel maintenance API backed by a local marker, respecting Evo site_status. */
class MaintenanceMode implements MaintenanceModeContract
{
    public function __construct(private CoreInterface $app) {}

    public function activate(array $payload): void
    {
        $this->app['files']->put($this->app->storagePath('framework-down.json'), json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function deactivate(): void
    {
        $this->app['files']->delete($this->app->storagePath('framework-down.json'));
    }

    public function active(): bool
    {
        return $this->app['files']->exists($this->app->storagePath('framework-down.json'))
            || (int) $this->app->getConfig('site_status', 0) === 0;
    }

    public function data(): array
    {
        $path = $this->app->storagePath('framework-down.json');
        return is_file($path) ? json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : [];
    }
}
