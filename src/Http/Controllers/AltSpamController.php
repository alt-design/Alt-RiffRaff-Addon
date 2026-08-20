<?php

declare(strict_types=1);

namespace AltDesign\RiffRaff\Http\Controllers;

use AltDesign\RiffRaff\Support\RiffRaff;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Statamic\Facades\Form;
use Statamic\Facades\YAML;
use Statamic\Fields\BlueprintRepository;
use Statamic\Filesystem\Manager;
use Statamic\Forms\Submission;

class AltSpamController
{
    protected array $data = [];

    public function index()
    {
        $manager = new Manager;

        if (! $manager->disk()->exists('content/riffraff')) {
            $manager->disk()->makeDirectory('content/riffraff');
        }

        $allSubmissions = File::allFiles(app_path() . '/../content/riffraff');
        $allSubmissions = collect($allSubmissions)->sortByDesc(function ($file) {
            return $file->getCTime();
        });

        foreach ($allSubmissions as $submission) {
            $data = YAML::parse(File::get($submission));
            $data['preview'] = $this->buildPreview($data['data'] ?? null);
            $this->data[] = $data;
        }

        $blueprint = with(new BlueprintRepository)
            ->setDirectory(
                __DIR__ . '/../../../resources/blueprints'
            )->find('riffraff');

        $fields = $blueprint->fields()->addValues($this->data);

        $fields = $fields->preProcess();

        return view('alt-riffraff::index', [
            'blueprint' => $blueprint->toPublishArray(),
            'values' => $fields->values(),
            'meta' => $fields->meta(),
            'data' => $this->data,
            'usageView' => $this->buildUsageView($this->fetchUsage()),
        ]);
    }

    private function buildPreview(mixed $data): string
    {
        if (is_array($data)) {
            $source = $data['message'] ?? null;

            if (! is_string($source)) {
                $source = json_encode($data) ?: '';
            }
        } elseif (is_string($data)) {
            $source = $data;
        } else {
            $source = '';
        }

        if ($source === '') {
            return '';
        }

        return mb_substr($source, 0, 50) . '...';
    }

    private function buildUsageView(?array $usage): ?array
    {
        if ($usage === null) {
            return null;
        }

        $isUnlimited = (bool) ($usage['plan']['is_unlimited'] ?? false);
        $percentUsed = (int) ($usage['percent_used'] ?? 0);
        $isOverQuota = ! $isUnlimited && ($usage['remaining'] ?? null) === 0;
        $isNearCap = ! $isUnlimited && ! $isOverQuota && $percentUsed >= 80;

        $barColorClass = match (true) {
            $isOverQuota => 'rr:bg-red-500',
            $isNearCap => 'rr:bg-orange-500',
            default => 'rr:bg-green-500',
        };

        $textColorClass = match (true) {
            $isOverQuota => 'rr:text-red-600',
            $isNearCap => 'rr:text-orange-600',
            default => 'rr:text-gray-800',
        };

        $used = number_format((int) ($usage['used'] ?? 0));

        if ($isUnlimited) {
            $headline = $used . ' checks this month · Unlimited';
        } else {
            $limit = number_format((int) ($usage['limit'] ?? 0));
            $headline = $used . ' / ' . $limit . ' checks used';
        }

        $resetsAt = $usage['period']['resets_at'] ?? null;
        $resetsAtFormatted = null;

        if (is_string($resetsAt) && $resetsAt !== '') {
            try {
                $resetsAtFormatted = Carbon::parse($resetsAt)->format('j M Y');
            } catch (\Exception) {
                $resetsAtFormatted = null;
            }
        }

        $clampedPercent = max(0, min(100, $percentUsed));

        return [
            'plan_name' => $usage['plan']['name'] ?? null,
            'is_exempt' => (bool) ($usage['is_exempt'] ?? false),
            'is_unlimited' => $isUnlimited,
            'headline' => $headline,
            'percent_used' => $percentUsed,
            'bar_width_style' => 'width: ' . $clampedPercent . '%',
            'bar_color_class' => $barColorClass,
            'text_color_class' => $textColorClass,
            'is_over_quota' => $isOverQuota,
            'is_near_cap' => $isNearCap,
            'resets_at_formatted' => $resetsAtFormatted,
        ];
    }

    private function fetchUsage(): ?array
    {
        $token = RiffRaff::token();

        if (empty($token)) {
            return null;
        }

        return Cache::remember(
            'alt-riffraff.usage.' . sha1($token),
            now()->addSeconds(30),
            function () use ($token): ?array {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ])->get(RiffRaff::url('api_usage_path'));

                if ($response->failed()) {
                    return null;
                }

                return $response->json();
            }
        );
    }

    public function destroy(string $id): RedirectResponse
    {
        $manager = new Manager;

        if ($id === 'all') {
            if ($manager->disk()->exists('content/riffraff')) {
                $files = $manager->disk()->getFiles('content/riffraff');

                foreach ($files as $file) {
                    $manager->disk()->delete($file);
                }
            }

            return redirect()->route('riffraff.index')->with('success', 'All suspected spam deleted.');
        }

        if ($manager->disk()->exists('content/riffraff/' . $id . '.yaml')) {
            $manager->disk()->delete('content/riffraff/' . $id . '.yaml');
        }

        return redirect()->route('riffraff.index')->with('success', 'Submission deleted.');
    }

    public function store(string $id): RedirectResponse
    {
        $manager = new Manager;

        if (! $manager->disk()->exists('content/riffraff')) {
            $manager->disk()->makeDirectory('content/riffraff');
        }

        $submission = File::get(app_path() . '/../content/riffraff/' . $id . '.yaml');
        $submission = YAML::parse($submission);

        $form = Form::find($submission['form_slug']);

        $data = collect($submission['data']);

        $submission = new Submission;
        $submission->form($form)->data($data)->save();

        if ($manager->disk()->exists('content/riffraff/' . $id . '.yaml')) {
            $manager->disk()->delete('content/riffraff/' . $id . '.yaml');
        }

        return redirect()->route('riffraff.index')->with('success', 'Submission released.');
    }

    public function show(string $id)
    {
        $manager = new Manager;

        if (! $manager->disk()->exists('content/riffraff')) {
            $manager->disk()->makeDirectory('content/riffraff');
        }

        $submission = File::get(app_path() . '/../content/riffraff/' . $id . '.yaml');
        $submission = YAML::parse($submission);

        return view('alt-riffraff::show', [
            'id' => $submission['id'],
            'submission' => $submission,
            'form' => Form::find($submission['form_slug']),
            'data' => collect($submission['data']),
            'score' => (int) $submission['spam_score'],
            'threshold' => (int) $submission['threshold'],
        ]);
    }
}
