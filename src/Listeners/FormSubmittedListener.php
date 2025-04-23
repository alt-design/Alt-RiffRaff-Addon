<?php

declare(strict_types=1);

namespace AltDesign\RiffRaff\Listeners;

use Illuminate\Support\Facades\Http;
use Statamic\Events\FormSubmitted;
use Statamic\Facades\YAML;
use Statamic\Filesystem\Manager;

class FormSubmittedListener
{
    public function handle(FormSubmitted $event): bool
    {
        $apiEmail = config('alt-riffraff-addon.api_email', '');
        $apiPassword = config('alt-riffraff-addon.api_password', '');
        $apiAuthenticationEndpoint = config('alt-riffraff-addon.api_authentication_endpoint', '');
        $apiEvaluateEndpoint = config('alt-riffraff-addon.api_evaluate_endpoint', '');

        if (empty($apiEmail) || empty($apiPassword)) {
            return true;
        }

        $formData = $event->submission->data()->all();

        $formDataString = array_reduce($formData, function (string $carry, string $item): string {
            return $carry . ' ' . $item;
        }, '');

        $authResponse = Http::withHeaders([
            'Accept' => 'application/json',
        ])->post($apiAuthenticationEndpoint, [
            'email' => $apiEmail,
            'password' => $apiPassword,
        ]);

        if ($authResponse->failed()) {
            return true;
        }

        $token = $authResponse->json()['data'];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->post($apiEvaluateEndpoint, [
            'content' => $formDataString,
        ]);

        if ($response->failed()) {
            return true;
        }

        $isSpam = $response->json('is_spam');
        $spamScore = $response->json('score');
        $threshold = $response->json('threshold');

        if ($isSpam) {
            $manager = new Manager;

            if (! $manager->disk()->exists('content/riffraff')) {
                $manager->disk()->makeDirectory('content/riffraff');
            }

            $submissionId = $event->submission->id();

            $manager->disk()->put('content/riffraff/' . $submissionId . '.yaml', Yaml::dump([
                'id' => $submissionId,
                'data' => $event->submission->data()->all(),
                'spam_score' => $spamScore,
                'threshold' => $threshold,
                'form_slug' => $event->submission->form()->handle(),
                'is_spam' => (int) $spamScore > (int) $threshold,
            ]));

            return false;
        }

        return true;
    }
}
