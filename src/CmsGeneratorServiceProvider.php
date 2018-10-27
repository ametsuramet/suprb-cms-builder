<?php

namespace Suprb\CmsGenerator;

use Illuminate\Support\ServiceProvider;

class CmsGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        // $this->publishes([
        //     __DIR__ . '/../config/cmsgenerator.php' => config_path('cmsgenerator.php'),
        // ], 'cmsbuilder');

        $this->publishes([
            __DIR__ . '/cmsbuilder.json' => base_path('/cmsbuilder.json'),
        ], 'cmsbuilder-json');

        $this->publishes([
            __DIR__ . '/assets/notfound.jpeg' => public_path('/images/notfound.jpeg'),
        ], 'cmsbuilder-json');

        // $this->publishes([
        //     __DIR__ . '/stubs/' => base_path('resources/cms-generator/'),
        // ], 'cmsbuilder');

        $this->publishes([
            __DIR__ . '/stubs/views/auth' => resource_path('views/auth'),
        ], 'cmsbuilder-auth-view');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->commands(
            'Suprb\CmsGenerator\Commands\CMSGeneratorCommand'
            // 'Suprb\CmsGenerator\Commands\CrudControllerCommand',
            // 'Suprb\CmsGenerator\Commands\CrudModelCommand',
            // 'Suprb\CmsGenerator\Commands\CrudMigrationCommand',
            // 'Suprb\CmsGenerator\Commands\CrudViewCommand',
            // 'Suprb\CmsGenerator\Commands\CrudLangCommand',
            // 'Suprb\CmsGenerator\Commands\CrudApiCommand',
            // 'Suprb\CmsGenerator\Commands\CrudApiControllerCommand'
        );
    }
}
