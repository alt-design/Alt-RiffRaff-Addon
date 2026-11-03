<?php

declare(strict_types=1);

namespace AltDesign\RiffRaff\Connect;

use AltDesign\RiffRaff\Support\RiffRaff;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use Statamic\Addons\FileSettings;
use Statamic\Facades\Addon;
use Statamic\Facades\Path;
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

    private const GIT_TIMEOUT_SECONDS = 10;

    private ?string $gitFailure = null;

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

    /**
     * False when something listening for the save stopped it.
     */
    public function save(#[\SensitiveParameter] string $key): bool
    {
        return Addon::get(self::ADDON_ID)->settings()->set('api_key', $key)->save();
    }

    /**
     * Why saving a key here would be unsafe or would not take effect, and
     * what is worth knowing but should not stop it.
     *
     * @return array{problems: list<string>, warnings: list<string>}
     */
    public function assess(): array
    {
        $problems = [];
        $warnings = [];

        if ((string) config('alt-riffraff.api_key', '') !== '') {
            $problems[] = 'ALT_RIFFRAFF_API_KEY is set in the environment, and it always wins over a saved key, so a saved key would not be used. Remove it from the environment first, or run with --force to save the key anyway.';
        }

        $settingsFile = $this->settingsFile();

        if ($settingsFile === null) {
            return ['problems' => $problems, 'warnings' => $warnings];
        }

        $relativeFile = $this->relativeToBase($settingsFile);
        $gitStatus = $this->gitStatus($relativeFile);
        $automation = $this->gitAutomationCovers($settingsFile);
        $gitProblem = $this->gitProblem($gitStatus, $relativeFile, $automation);

        if ($gitProblem !== null) {
            $problems[] = $gitProblem;
        }

        if ($gitStatus === GitStatus::NotIgnored && ! $automation) {
            $warnings[] = "{$relativeFile} is not in the site's .gitignore, so a git add on this server would commit the key. Add it to .gitignore in the site's repository.";
        }

        if ($this->isInsideRelease($settingsFile)) {
            $problems[] = "This site deploys with zero downtime and resources/addons is inside the release directory, so the key would be lost on the next deploy. Make resources/addons a shared path in Forge first (the addon's documentation covers what that changes), or run with --force.";
        }

        return ['problems' => $problems, 'warnings' => $warnings];
    }

    private function gitProblem(GitStatus $status, string $relativeFile, bool $automation): ?string
    {
        return match (true) {
            $status === GitStatus::Tracked => "{$relativeFile} is tracked in this site's git repository, so the key would end up in a commit. Remove the file from the repository and add it to .gitignore in a normal deploy first, or run with --force.",
            $status === GitStatus::NotIgnored && $automation => "Statamic's git automation covers {$relativeFile} and the file is not in .gitignore, so saving the key would commit it, and push it if pushing is on. Add the file to the site's .gitignore and deploy first, or run with --force.",
            $status === GitStatus::Unknown => "Could not check with git whether {$relativeFile} would be committed ({$this->gitFailure}). Run with --force if you are sure it will not be.",
            default => null,
        };
    }

    private function settingsFile(): ?string
    {
        if (! $this->canSave()) {
            return null;
        }

        $settings = Addon::get(self::ADDON_ID)->settings();

        return $settings instanceof FileSettings ? (string) $settings->path() : null;
    }

    /**
     * Paths are resolved the way Statamic's own git automation resolves them,
     * so a relative entry or one with `..` in it is matched as it would be.
     */
    private function gitAutomationCovers(string $settingsFile): bool
    {
        if (! config('statamic.git.enabled')) {
            return false;
        }

        $file = Path::resolve($settingsFile);

        foreach ((array) config('statamic.git.paths', []) as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $tracked = rtrim(Path::resolve(Path::isAbsolute(Path::tidy($path)) ? $path : $this->basePath() . '/' . $path), '/');

            if ($file === $tracked || str_starts_with($file, $tracked . '/')) {
                return true;
            }
        }

        return false;
    }

    private function gitStatus(string $relativeFile): GitStatus
    {
        $repository = $this->git(['rev-parse', '--is-inside-work-tree']);

        if ($repository === null) {
            return GitStatus::Unknown;
        }

        if (! $repository->successful()) {
            return str_contains(strtolower($repository->errorOutput()), 'not a git repository')
                ? GitStatus::NoRepository
                : $this->gitFailed($repository);
        }

        $tracked = $this->git(['ls-files', '--error-unmatch', '--', $relativeFile]);

        if ($tracked === null) {
            return GitStatus::Unknown;
        }

        if ($tracked->successful()) {
            return GitStatus::Tracked;
        }

        if ($tracked->exitCode() !== 1) {
            return $this->gitFailed($tracked);
        }

        $ignored = $this->git(['check-ignore', '-q', '--', $relativeFile]);

        return match ($ignored?->exitCode()) {
            0 => GitStatus::Ignored,
            1 => GitStatus::NotIgnored,
            null => GitStatus::Unknown,
            default => $this->gitFailed($ignored),
        };
    }

    /**
     * @param  list<string>  $arguments
     */
    private function git(array $arguments): ?ProcessResult
    {
        try {
            return Process::path($this->basePath())
                ->timeout(self::GIT_TIMEOUT_SECONDS)
                ->run(['git', ...$arguments]);
        } catch (Throwable $e) {
            $this->gitFailure = 'git could not be run: ' . $e::class;

            return null;
        }
    }

    private function gitFailed(ProcessResult $result): GitStatus
    {
        $firstLine = trim(strtok(trim($result->errorOutput()), "\n") ?: '');
        $this->gitFailure = $firstLine === '' ? 'git exited with ' . $result->exitCode() : $firstLine;

        return GitStatus::Unknown;
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
