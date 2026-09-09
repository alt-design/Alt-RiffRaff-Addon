<?php

declare(strict_types=1);

namespace AltDesign\RiffRaff\Listeners;

use AltDesign\RiffRaff\Support\RiffRaff;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Statamic\Events\FormSubmitted;
use Statamic\Facades\YAML;
use Statamic\Fields\Field;
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

        $contentData = Arr::except($formData, $this->excludedFields($event));

        $formDataString = array_reduce($contentData, function (string $carry, string|array $item): string {
            if (is_array($item)) {
                return $carry;
            }

            return $carry . ' ' . $item;
        }, '');

        $payload = ['content' => $formDataString];

        if ($email = $this->resolveEmail($event, $contentData)) {
            $payload['email'] = $email;
        }

        if ($subject = $this->resolveSubject($contentData)) {
            $payload['subject'] = $subject;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->post(RiffRaff::url('api_evaluate_path'), $payload);

        if ($response->failed()) {
            return true;
        }

        $isSpam = (bool) $response->json('is_spam');
        $spamScore = $response->json('score');
        $threshold = $response->json('threshold');
        $reasons = $response->json('reasons', []);

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
                'reasons' => $reasons,
                'form_slug' => $event->submission->form()->handle(),
                'is_spam' => $isSpam,
                'flagged_at' => now()->toIso8601String(),
            ]));

            return false;
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function excludedFields(FormSubmitted $event): array
    {
        $excluded = (array) config('alt-riffraff.excluded_content_fields', []);

        $honeypot = $event->submission->form()->honeypot();

        if (is_string($honeypot) && $honeypot !== '') {
            $excluded[] = $honeypot;
        }

        return array_unique($excluded);
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    private function resolveEmail(FormSubmitted $event, array $formData): ?string
    {
        $handle = $this->emailFieldHandle($event);

        if ($handle !== null && is_string($formData[$handle] ?? null)) {
            $value = $formData[$handle];

            if (filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
                return $value;
            }
        }

        foreach ($formData as $value) {
            if (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
                return $value;
            }
        }

        return null;
    }

    private function emailFieldHandle(FormSubmitted $event): ?string
    {
        $field = $event->submission->form()->blueprint()->fields()->all()
            ->first(fn (Field $field): bool => $this->isEmailField($field));

        return $field?->handle();
    }

    private function isEmailField(Field $field): bool
    {
        if ($field->type() === 'email') {
            return true;
        }

        if ($field->get('input_type') === 'email') {
            return true;
        }

        return $this->hasEmailValidationRule($field);
    }

    private function hasEmailValidationRule(Field $field): bool
    {
        $validate = $field->get('validate');

        if (is_string($validate)) {
            return in_array('email', explode('|', $validate), true);
        }

        if (is_array($validate)) {
            return in_array('email', $validate, true);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    private function resolveSubject(array $formData): ?string
    {
        $subject = $formData['subject'] ?? null;

        if (is_string($subject) && $subject !== '') {
            return $subject;
        }

        return null;
    }
}
