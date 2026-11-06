<?php

declare(strict_types=1);

namespace AltDesign\RiffRaff;

use AltDesign\RiffRaff\Console\Commands\ConnectCommand;
use AltDesign\RiffRaff\Console\Commands\StatusCommand;
use AltDesign\RiffRaff\Listeners\FormSubmittedListener;
use AltDesign\RiffRaff\Support\AltToolsNav;
use AltDesign\RiffRaff\Support\FormTiming;
use Closure;
use Illuminate\Support\Facades\Event;
use Statamic\Events\FormSubmitted;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Forms\Tags as FormTags;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Statamic;

class ServiceProvider extends AddonServiceProvider
{
    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
    ];

    protected $commands = [
        ConnectCommand::class,
        StatusCommand::class,
    ];

    protected $vite = [
        'input' => [
            'resources/css/riffraff.css',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    public function bootAddon(): void
    {
        parent::bootAddon();

        $this->setupPublishables();
        $this->addToNav();
        $this->registerPermissions();
        $this->registerEvents();
        $this->registerFormTimingField();
    }

    private function registerFormTimingField(): void
    {
        FormTags::hook('before-close', function ($payload, Closure $next) {
            if (is_array($payload)) {
                $payload['html'] .= FormTiming::field($this->getForm());
            }

            return $next($payload);
        });
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
            AltToolsNav::add($nav, 'spam', 'RiffRaff', 'riffraff.index', 'view riffraff', ['mail-inbox-content', 'email-utility']);
        });
    }

    private function setupPublishables(): void
    {
        $this->publishes([
            __DIR__.'/../config/alt-riffraff.php' => config_path('alt-riffraff.php'),
        ], 'alt-design/alt-riffraff');

        Statamic::afterInstalled(function ($command): void {
            $command->call('vendor:publish', ['--tag' => 'alt-design/alt-riffraff']);
        });
    }
}
