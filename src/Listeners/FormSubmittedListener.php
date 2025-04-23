<?php

declare(strict_types=1);

namespace AltDesign\SpamAddon\Listeners;

use Illuminate\Support\Facades\Http;
use Statamic\Events\FormSubmitted;
use Statamic\Facades\YAML;
use Statamic\Filesystem\Manager;

class FormSubmittedListener
{
    public function handle(FormSubmitted $event): bool
    {
        $apiEmail = config('spam-addon.api_email', '');
        $apiPassword = config('spam-addon.api_password', '');
        $apiAuthenticationEndpoint = config('spam-addon.api_authentication_endpoint', '');
        $apiEvaluateEndpoint = config('spam-addon.api_evaluate_endpoint', '');

        if (empty($apiEmail) || empty($apiPassword)) {
            // No API credentials provided in the config, so we can't check for spam
            // should we allow the content to be sent? or should we block/hold it?
            // false means we don't want to send the form

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
            // Couldn't authenticate with the API so we can't check for spam
            // should we allow the content to be sent? or should we block/hold it?
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
            // Couldn't evaluate the content so we can't check for spam
            // should we allow the content to be sent? or should we block/hold it?
            return true;
        }

        $isSpam = $response->json('is_spam');
        $spamScore = $response->json('score');
        $threshold = $response->json('threshold');

        if ($isSpam) {
            // Send a validation error if the spam score exceeds the threshold

            // Considered spam, so we should save it for review and not send it
            $manager = new Manager;

            if (! $manager->disk()->exists('content/alt-spam')) {
                $manager->disk()->makeDirectory('content/alt-spam');
            }

            $submissionId = $event->submission->id();

            $manager->disk()->put('content/alt-spam/' . $submissionId . '.yaml', Yaml::dump([
                'id' => $submissionId,
                'data' => $event->submission->data()->all(),
                'spam_score' => $spamScore,
                'threshold' => $threshold,
                'form_slug' => $event->submission->form()->handle(),
                'is_spam' => (int) $spamScore > (int) $threshold,
            ]));

            return false;
        }

        // Assuming we pass the spam check, we can proceed with the form submission
        return true;
    }
}
