<?php

declare(strict_types=1);

namespace AltDesign\RiffRaff\Listeners;

use AltDesign\RiffRaff\Support\RiffRaff;
use Illuminate\Support\Facades\Http;
use Statamic\Events\FormSubmitted;
use Statamic\Facades\YAML;
use Statamic\Filesystem\Manager;

class FormSubmittedListener
{
    public function handle(FormSubmitted $event): bool
    {
        $token = RiffRaff::token();

        if (empty($token)) {
            return true;
        }

        $formData = $event->submission->data()->all();

        $formData = array_filter($formData, fn ($item) => $item !== null);

        $formDataString = array_reduce($formData, function (string $carry, string|array $item): string {
            if (is_array($item)) {
                return $carry;
            }

            return $carry . ' ' . $item;
        }, '');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->post(RiffRaff::url('api_evaluate_path'), [
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

            $manager->disk()->put('content/riffraff/' . $submissionId . '.yaml', YAML::dump([
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
