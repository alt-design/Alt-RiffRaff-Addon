<?php

declare(strict_types=1);

namespace AltDesign\RiffRaff\Console\Commands;

use AltDesign\RiffRaff\Connect\ConnectionFailed;
use AltDesign\RiffRaff\Connect\DeviceFlow;
use AltDesign\RiffRaff\Connect\KeyStorage;
use AltDesign\RiffRaff\Connect\PendingConnection;
use AltDesign\RiffRaff\Connect\PollResult;
use AltDesign\RiffRaff\Connect\PollStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Sleep;
use Statamic\Console\RunsInPlease;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Throwable;

/**
 * Connects the site to RiffRaff without anyone copying a key: the command
 * prints a code, a team manager approves it in the dashboard, and the key is
 * saved to the addon settings. The key is never printed, so the command can
 * run through a runner that stores its output, such as Forge's Commands.
 */
class ConnectCommand extends Command
{
    use RunsInPlease;

    private const MAX_SITE_NAME_LENGTH = 100;

    protected $signature = 'statamic:riffraff:connect
        {name? : What to call this site in RiffRaff}
        {--wait= : Seconds to wait for approval before stopping. Defaults to waiting until the code expires}
        {--replace : Replace a key already saved in the addon settings}
        {--force : Save the key even where the command would normally refuse}';

    protected $description = 'Connect this site to RiffRaff by approving a code in the RiffRaff dashboard';

    public function handle(DeviceFlow $flow, KeyStorage $storage): int
    {
        if (! $storage->canSave()) {
            $this->components->error('Saving a key needs addon settings, which this version of Statamic does not have. Set ALT_RIFFRAFF_API_KEY in the environment instead.');

            return self::FAILURE;
        }

        if (! $flow->hasSecureConnection()) {
            $this->components->error('ALT_RIFFRAFF_BASE_URL must start with https://, because the key comes back over this connection.');

            return self::FAILURE;
        }

        $wait = $this->option('wait');

        if ($wait !== null && ! ctype_digit((string) $wait)) {
            $this->components->error('--wait takes a number of seconds, such as --wait=240.');

            return self::FAILURE;
        }

        if (! $this->safeToSave($storage)) {
            $this->line('Nothing was requested from RiffRaff.');

            return self::FAILURE;
        }

        $pending = $this->pendingConnection($flow, $wait === '0');

        if ($pending === null) {
            return self::FAILURE;
        }

        return $this->waitForApproval($flow, $storage, $pending['connection'], $pending['resumed'], $wait === null ? null : (int) $wait);
    }

    private function safeToSave(KeyStorage $storage): bool
    {
        $force = (bool) $this->option('force');

        if ($storage->hasSavedKey() && ! $force && ! $this->option('replace')) {
            $this->components->error('A key is already saved in the addon settings. Run with --replace to replace it.');

            return false;
        }

        ['problems' => $problems, 'warnings' => $warnings] = $storage->assess();

        if ($problems !== [] && ! $force) {
            foreach ($problems as $problem) {
                $this->components->error($this->escape($problem));
            }

            return false;
        }

        foreach ($problems as $problem) {
            $this->components->warn('Going ahead because of --force: ' . $this->escape($problem));
        }

        foreach ($warnings as $warning) {
            $this->components->warn($this->escape($warning));
        }

        return true;
    }

    /**
     * @return array{connection: PendingConnection, resumed: bool}|null
     */
    private function pendingConnection(DeviceFlow $flow, bool $finishingLater): ?array
    {
        $name = $this->siteName();
        $current = PendingConnection::current();

        if ($current !== null && ($name === null || $name === $current->siteName)) {
            $this->line('Picking up the request already waiting for approval for "' . $this->escape($current->siteName) . '".');
            $this->showCode($current);

            return ['connection' => $current, 'resumed' => true];
        }

        if ($name === null) {
            $this->components->error('Give the site a name, as it should appear in RiffRaff, for example: php please riffraff:connect "Acme website"');

            return null;
        }

        if (mb_strlen($name) > self::MAX_SITE_NAME_LENGTH || preg_match('/\p{C}/u', $name) !== 0) {
            $this->components->error('The site name must be at most ' . self::MAX_SITE_NAME_LENGTH . ' characters, with no control characters.');

            return null;
        }

        try {
            $connection = $flow->requestCode($name);
        } catch (ConnectionFailed $e) {
            $this->components->error($this->escape($e->getMessage()));

            return null;
        }

        if (! $connection->remember()) {
            $this->components->warn($finishingLater
                ? "This site's cache cannot keep the request until the next run, so running the command again will ask for a new code. Run it without --wait=0 and approve the code while it waits."
                : "This site's cache cannot keep the request until the next run, so approve the code while this command waits.");
        }

        $this->showCode($connection);

        return ['connection' => $connection, 'resumed' => false];
    }

    private function siteName(): ?string
    {
        $name = $this->argument('name');
        $name = is_string($name) ? trim($name) : '';

        return $name === '' ? null : $name;
    }

    private function showCode(PendingConnection $connection): void
    {
        $this->newLine();
        $this->line('A manager of your RiffRaff team needs to approve this site:');
        $this->newLine();
        $this->line('  1. Go to ' . $this->escape($connection->verificationUri));
        $this->line('  2. Enter the code <options=bold>' . $this->escape($connection->userCode) . '</>');
        $this->newLine();
        $this->line('The code expires at ' . $this->expiryTime($connection) . '.');
    }

    private function waitForApproval(DeviceFlow $flow, KeyStorage $storage, PendingConnection $connection, bool $pollNow, ?int $wait): int
    {
        $deadline = $wait === null
            ? $connection->expiresAt
            : min($connection->expiresAt, Carbon::now()->getTimestamp() + $wait);
        $warnedUnavailable = false;

        if ($wait !== 0) {
            $this->line('Waiting for approval...');
        }

        while (true) {
            if ($pollNow) {
                $result = $flow->poll($connection);
                $finished = $this->finish($result, $storage);

                if ($finished !== null) {
                    return $finished;
                }

                if ($result->status === PollStatus::SlowDown) {
                    $connection = $connection->withInterval((int) $result->interval);
                    $connection->remember();
                }

                if ($result->status === PollStatus::Unavailable && ! $warnedUnavailable) {
                    $this->components->warn('RiffRaff could not be reached just now. Still trying.');
                    $warnedUnavailable = true;
                }
            }

            $pollNow = true;

            if (Carbon::now()->getTimestamp() + $connection->interval > $deadline) {
                break;
            }

            Sleep::for($connection->interval)->seconds();
        }

        if ($connection->hasExpired()) {
            PendingConnection::forget();
            $this->components->error('The code expired before it was approved. Run the command again for a new code.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Not approved yet. Once it is, run php please riffraff:connect again to finish connecting. The code expires at ' . $this->expiryTime($connection) . '.');

        return self::SUCCESS;
    }

    /**
     * The exit code once the request has reached an outcome, or null while it
     * is still waiting.
     */
    private function finish(PollResult $result, KeyStorage $storage): ?int
    {
        return match ($result->status) {
            PollStatus::Approved => $this->saveKey($result, $storage),
            PollStatus::Denied => $this->abandon('The request was denied in RiffRaff.'),
            PollStatus::Expired => $this->abandon('The code expired before it was approved. Run the command again for a new code.'),
            PollStatus::Unrecognised => $this->abandon('RiffRaff no longer recognises this request. It may already have been collected. Run php please riffraff:status to check, or run this command again for a new code.'),
            PollStatus::Failed => $this->abandon('RiffRaff refused to finish connecting: ' . $this->escape((string) $result->message)),
            default => null,
        };
    }

    private function saveKey(PollResult $result, KeyStorage $storage): int
    {
        PendingConnection::forget();
        $unsaved = 'Fix that, then run the command again for a new key, and revoke the unused one for this site under Settings, Sites in RiffRaff.';

        try {
            $saved = $storage->save((string) $result->key);
        } catch (Throwable $e) {
            $this->components->error('RiffRaff approved the request, but the key could not be saved (' . $e::class . '). ' . $unsaved);

            return self::FAILURE;
        }

        if (! $saved) {
            $this->components->error('RiffRaff approved the request, but something on this site stopped the addon settings being saved. ' . $unsaved);

            return self::FAILURE;
        }

        $this->components->info('Connected as ' . $this->escape((string) $result->site) . '.');

        return self::SUCCESS;
    }

    private function abandon(string $message): int
    {
        PendingConnection::forget();
        $this->components->error($message);

        return self::FAILURE;
    }

    private function expiryTime(PendingConnection $connection): string
    {
        return Carbon::createFromTimestamp($connection->expiresAt, config('app.timezone'))->format('H:i');
    }

    /**
     * Text that came from RiffRaff or git, made safe to print: no console
     * formatting tags and no control characters.
     */
    private function escape(string $text): string
    {
        return OutputFormatter::escape((string) preg_replace('/\p{C}/u', '', $text));
    }
}
