<?php

declare(strict_types=1);

namespace AltDesign\RiffRaff\Console\Commands;

use AltDesign\RiffRaff\Connect\KeyStorage;
use AltDesign\RiffRaff\Support\RiffRaff;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Statamic\Console\RunsInPlease;

/**
 * Says where the site's key comes from and whether RiffRaff accepts it,
 * without printing the key.
 */
class StatusCommand extends Command
{
    use RunsInPlease;

    private const TIMEOUT_SECONDS = 15;

    protected $signature = 'statamic:riffraff:status';

    protected $description = 'Show where the RiffRaff key comes from and whether it works';

    public function handle(KeyStorage $storage): int
    {
        $fromEnvironment = (string) config('alt-riffraff.api_key', '') !== '';
        $saved = $storage->hasSavedKey();
        $login = (string) config('alt-riffraff.api_email', '') !== '' && (string) config('alt-riffraff.api_password', '') !== '';

        if (! $fromEnvironment && ! $saved && ! $login) {
            $this->components->error('No key is configured, so form submissions are not being checked. Run php please riffraff:connect "Site name" to connect this site.');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Key', match (true) {
            $fromEnvironment => 'From the environment (ALT_RIFFRAFF_API_KEY)',
            $saved => 'Saved in the addon settings',
            default => 'Signing in with ALT_RIFFRAFF_EMAIL and ALT_RIFFRAFF_PASSWORD',
        });

        if ($fromEnvironment && $saved) {
            $this->components->warn('A key is also saved in the addon settings. It is not used while ALT_RIFFRAFF_API_KEY is set.');
        }

        $token = RiffRaff::token();

        if ($token === null || $token === '') {
            $this->components->error('Signing in to RiffRaff failed. Check ALT_RIFFRAFF_EMAIL and ALT_RIFFRAFF_PASSWORD.');

            return self::FAILURE;
        }

        try {
            $response = Http::acceptJson()
                ->timeout(self::TIMEOUT_SECONDS)
                ->withToken($token)
                ->get(RiffRaff::url('api_usage_path', '/api/usage'));
        } catch (ConnectionException) {
            $this->components->error('Could not reach RiffRaff at ' . rtrim((string) config('alt-riffraff.api_base_url'), '/') . '.');

            return self::FAILURE;
        }

        if ($response->status() === 401) {
            $this->components->error('RiffRaff does not accept this key. It may have been revoked or replaced. Run php please riffraff:connect "Site name" --force to get a new one.');

            return self::FAILURE;
        }

        if ($response->failed()) {
            $this->components->error('RiffRaff answered with HTTP status ' . $response->status() . '.');

            return self::FAILURE;
        }

        $this->showUsage($response);

        return self::SUCCESS;
    }

    private function showUsage(Response $response): void
    {
        $site = $response->json('site');
        $plan = $response->json('plan.name');

        $this->components->twoColumnDetail('Works', '<fg=green>Yes</>');
        $this->components->twoColumnDetail('Site', is_string($site) && $site !== '' ? $site : 'Not linked to a site');
        $this->components->twoColumnDetail('Plan', is_string($plan) && $plan !== '' ? $plan : 'None');
        $this->components->twoColumnDetail('Used this month', $this->usage($response));
    }

    private function usage(Response $response): string
    {
        $used = $response->json('used');
        $limit = $response->json('limit');

        if (! is_int($used)) {
            return 'Unknown';
        }

        if (! is_int($limit)) {
            return number_format($used) . ', with no monthly limit';
        }

        $percent = $response->json('percent_used');

        return number_format($used) . ' of ' . number_format($limit) . (is_int($percent) ? " ({$percent}%)" : '');
    }
}
