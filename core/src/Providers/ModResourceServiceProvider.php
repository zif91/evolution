<?php namespace EvolutionCMS\Providers;

use EvolutionCMS\ServiceProvider;
use modResource;

class ModResourceServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('modResource', function ($modx) {
            // Prefer an installed legacy implementation; otherwise use the bundled MODxAPI.
            $class = class_exists(\modResource::class)
                ? \modResource::class
                : \Pathologic\EvolutionCMS\MODxAPI\modResource::class;
            return new $class($modx);
        });

        $this->app->setEvolutionProperty('modResource', 'doc');
    }
}
