<?php namespace Igaster\LaravelTheme;

use Igaster\LaravelTheme\Commands\createPackage;
use Igaster\LaravelTheme\Commands\createTheme;
use Igaster\LaravelTheme\Commands\installPackage;
use Igaster\LaravelTheme\Commands\listThemes;
use Igaster\LaravelTheme\Commands\refreshCache;
use Igaster\LaravelTheme\Commands\removeTheme;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class themeServiceProvider extends ServiceProvider
{

    public function register()
    {

        /*--------------------------------------------------------------------------
        | Bind in IOC
        |--------------------------------------------------------------------------*/

        $this->app->singleton('igaster.themes', function () {
            return new Themes();
        });

        /*--------------------------------------------------------------------------
        | Replace FileViewFinder
        |--------------------------------------------------------------------------*/

        $this->app->singleton('view.finder', function ($app) {
            return new \Igaster\LaravelTheme\themeViewFinder(
                $app['files'],
                $app['config']['view.paths'],
                null
            );
        });

        /*--------------------------------------------------------------------------
        | Register helpers.php functions
        |--------------------------------------------------------------------------*/

        require_once 'Helpers/helpers.php';

    }

    public function boot()
    {

        /*--------------------------------------------------------------------------
        | Initialize Themes
        |--------------------------------------------------------------------------*/

        $themes = $this->app->make('igaster.themes');
        $themes->scanThemes();

        /*--------------------------------------------------------------------------
        | Activate default theme
        |--------------------------------------------------------------------------*/
        if (!$themes->current() && \Config::get('themes.default')) {
            $themes->set(\Config::get('themes.default'));
        }

        /*--------------------------------------------------------------------------
        | Pulish configuration file
        |--------------------------------------------------------------------------*/

        $this->publishes([
            __DIR__ . '/Config/themes.php' => config_path('themes.php'),
        ], 'laravel-theme');

        /*--------------------------------------------------------------------------
        | Register Console Commands
        |--------------------------------------------------------------------------*/
        if ($this->app->runningInConsole()) {
            $this->commands([
                listThemes::class,
                createTheme::class,
                removeTheme::class,
                createPackage::class,
                installPackage::class,
                refreshCache::class,
            ]);
        }
    }
}
