<?php

declare(strict_types=1);

namespace AltDesign\RiffRaff\Connect;

use AltDesign\RiffRaff\Support\RiffRaff;
use Illuminate\Support\Facades\Process;
use Statamic\Addons\FileSettings;
use Statamic\Facades\Addon;
use Throwable;

/**
 * Saves a key to the addon settings, and says why doing so on this site would
 * be unsafe or pointless before anything is requested from RiffRaff.
 *
 * It refuses rather than adding a .gitignore entry on the server: an edited,
 * tracked .gitignore can make the next deploy's git pull fail, and ignoring a
 * file that is already tracked has no effect.
 */
class KeyStorage
{
    private const ADDON_ID = 'alt-design/alt-riffraff';

    public function __construct(private readonly ?string $basePath = null) {}

    /**
     * Addon settings arrived in Statamic 6, so a Statamic 5 site has nowhere
     * to save a key except the environment.
     */
    public function canSave(): bool
    {
        $addon = Addon::get(self::ADDON_ID);

        return $addon !== null && method_exists($addon, 'settings');
    }

    public function hasSavedKey(): bool
    {
        return $this->canSave() && RiffRaff::apiKeyFromSettings() !== null;
    }

    public function save(#[\SensitiveParameter] string $key): void
    {
        Addon::get(self::ADDON_ID)->settings()->set('api_key', $key)->save();
    }

    /**
     * Why saving a key here would be unsafe or would not take effect. Empty
     * when it is fine to go ahead.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];

        if ($this->hasSavedKey()) {
            $problems[] = 'A key is already saved in the addon settings. Run with --force to replace it.';
        }

        if ((string) config('alt-riffraff.api_key', '') !== '') {
            $problems[] = 'ALT_RIFFRAFF_API_KEY is set in the environment, and it always wins over a saved key, so a saved key would not be used. Remove it from the environment first, or run with --force to save the key anyway.';
        }

        $settingsFile = $this->settingsFile();

        if ($settingsFile === null) {
            return $problems;
        }

        $relativeFile = $this->relativeToBase($settingsFile);

        if ($this->gitAutomationCovers($settingsFile)) {
            $problems[] = "Statamic's git automation is on and covers {$relativeFile}, so saving the key would commit it, and push it if pushing is on. Take resources/addons out of statamic.git.paths in config/statamic/git.php first, or run with --force.";
        }

        if ($this->isTracked($relativeFile)) {
            $problems[] = "{$relativeFile} is tracked in this site's git repository, so the key would end up in a commit. Remove the file from the repository and add it to .gitignore in a normal deploy first, or run with --force.";
        }

        if ($this->isInsideRelease($settingsFile)) {
            $problems[] = 'This site deploys with zero downtime and resources/addons is inside the release directory, so the key would be lost on the next deploy. Add resources/addons to the site\'s shared paths in Forge first, copying what is in it now into the shared directory, or run with --force.';
        }

        return $problems;
    }

    private function settingsFile(): ?string
    {
        if (! $this->canSave()) {
            return null;
        }

        $settings = Addon::get(self::ADDON_ID)->settings();

        return $settings instanceof FileSettings ? (string) $settings->path() : null;
    }

    private function gitAutomationCovers(string $settingsFile): bool
    {
        if (! config('statamic.git.enabled')) {
            return false;
        }

        foreach ((array) config('statamic.git.paths', []) as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $path = rtrim($path, '/');

            if ($settingsFile === $path || str_starts_with($settingsFile, $path . '/')) {
                return true;
            }
        }

        return false;
    }

    private function isTracked(string $relativeFile): bool
    {
        try {
            return Process::path($this->basePath())
                ->timeout(10)
                ->run(['git', 'ls-files', '--error-unmatch', '--', $relativeFile])
                ->successful();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * A zero downtime deploy runs each release from releases/<id>, with a
     * `current` link pointing at the live one, and starts every release from
     * a fresh checkout. A settings directory that resolves inside the
     * release, rather than through a shared path, starts empty each time.
     */
    private function isInsideRelease(string $settingsFile): bool
    {
        $release = realpath($this->basePath());

        if ($release === false || preg_match('#^(.+)/releases/[^/]+$#', $release, $matches) !== 1) {
            return false;
        }

        if (! is_link($matches[1] . '/current')) {
            return false;
        }

        $directory = $this->resolveDirectory(dirname($settingsFile));

        return $directory === $release || str_starts_with($directory, $release . '/');
    }

    private function resolveDirectory(string $directory): string
    {
        $resolved = realpath($directory);

        if ($resolved !== false) {
            return $resolved;
        }

        $parent = realpath(dirname($directory));

        return $parent === false ? $directory : $parent . '/' . basename($directory);
    }

    private function relativeToBase(string $path): string
    {
        $base = rtrim($this->basePath(), '/') . '/';

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }

    private function basePath(): string
    {
        return $this->basePath ?? base_path();
    }
}
