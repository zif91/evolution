<?php namespace EvolutionCMS\Providers;

use EvolutionCMS\ServiceProvider;
use modUsers;

class ModUsersServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('modUsers', function ($modx) {
            // Prefer an installed legacy implementation; otherwise use the bundled MODxAPI.
            $class = class_exists(\modUsers::class)
                ? \modUsers::class
                : \Pathologic\EvolutionCMS\MODxAPI\modUsers::class;
            return new $class($modx);
        });

        $this->app->setEvolutionProperty('modUsers', 'user');
    }
}
