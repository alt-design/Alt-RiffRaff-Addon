<?php

declare(strict_types=1);

namespace AltDesign\RiffRaff;

use AltDesign\RiffRaff\Listeners\FormSubmittedListener;
use Edalzell\Forma\ConfigController;
use Edalzell\Forma\Forma;
use Illuminate\Support\Facades\Event;
use Statamic\Events\FormSubmitted;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Statamic;

class ServiceProvider extends AddonServiceProvider
{
    protected $routes = [
        'cp' => __DIR__ . '/../routes/cp.php',
    ];

    protected $vite = [
        'input' => [
            'resources/js/riffraff.js',
            'resources/css/riffraff.css',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    public function bootAddon(): void
    {
        parent::bootAddon();

        $this->setupForma();
        $this->setupPublishables();
        $this->addToNav();
        $this->registerPermissions();
        $this->registerEvents();
    }

    private function registerEvents(): void
    {
        Event::listen(
            FormSubmitted::class,
            FormSubmittedListener::class,
        );
    }

    private function registerPermissions(): void
    {
        Permission::register('view riffraff')->label('View RiffRaff');
    }

    private function addToNav(): void
    {
        Nav::extend(function ($nav): void {
            $nav->content('RiffRaff')
                ->section('Tools')
                ->can('view riffraff')
                ->route('riffraff.index')
                ->icon('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg>');
        });
    }

    private function setupPublishables(): void
    {
        $this->publishes([
            __DIR__ . '/../config/alt-riffraff-addon.php' => config_path('alt-riffraff-addon.php'),
        ], 'alt-design/alt-riffraff-addon');

        Statamic::afterInstalled(function ($command): void {
            $command->call('vendor:publish', ['--tag' => 'alt-design/alt-riffraff-addon']);
        });
    }

    private function setupForma(): void
    {
        Forma::add('alt-design/alt-riffraff-addon', ConfigController::class);
    }
}
