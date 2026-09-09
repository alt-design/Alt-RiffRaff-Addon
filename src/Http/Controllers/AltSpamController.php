<?php

declare(strict_types=1);

namespace AltDesign\RiffRaff\Http\Controllers;

use AltDesign\RiffRaff\Support\RiffRaff;
use AltDesign\RiffRaff\Support\TemplateGuard;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Statamic\Facades\Form;
use Statamic\Facades\YAML;
use Statamic\Filesystem\Manager;
use Statamic\Forms\Submission;

class AltSpamController
{
    private const STORAGE_PATH = 'content/riffraff';

    public function index(Request $request): View
    {
        $manager = new Manager;

        $submissions = $this->loadSubmissions($manager);

        $search = trim((string) $request->query('q', ''));
        $formFilter = trim((string) $request->query('form', ''));
        $sort = $request->query('sort') === 'score' ? 'score' : 'recent';

        $filtered = $submissions
            ->when($formFilter !== '', fn (Collection $items): Collection => $items->where('form_slug', $formFilter))
            ->when($search !== '', fn (Collection $items): Collection => $items->filter(
                fn (array $item): bool => $this->matchesSearch($item, $search)
            ));

        $sorted = $sort === 'score'
            ? $filtered->sortByDesc(fn (array $item): int => $item['score_view']['diff'])
            : $filtered->sortByDesc('flagged_at_timestamp');

        return view('alt-riffraff::index', [
            'submissions' => $sorted->values(),
            'totalCount' => $submissions->count(),
            'formOptions' => $submissions->pluck('form_slug')->unique()->sort()->values(),
            'search' => $search,
            'formFilter' => $formFilter,
            'sort' => $sort,
            'usageView' => $this->buildUsageView($this->fetchUsage()),
        ]);
    }

    public function show(string $id): View
    {
        $manager = new Manager;
        $path = self::STORAGE_PATH . '/' . $id . '.yaml';

        abort_unless($manager->disk()->exists($path), 404);

        $data = YAML::parse((string) $manager->disk()->get($path));
        $submission = $this->presentSubmission($data, $manager->disk()->lastModified($path));

        return view('alt-riffraff::show', [
            'submission' => $submission,
            'form' => Form::find($submission['form_slug']),
            'fields' => $this->humaniseFields($submission['data']),
            'raw' => TemplateGuard::breakMustaches(json_encode($submission['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}'),
        ]);
    }

    public function store(string $id): RedirectResponse
    {
        $manager = new Manager;
        $path = self::STORAGE_PATH . '/' . $id . '.yaml';

        if (! $manager->disk()->exists($path)) {
            return redirect(cp_route('riffraff.index'))->with('error', 'That submission has already been dealt with.');
        }

        $submission = YAML::parse((string) $manager->disk()->get($path));
        $form = Form::find($submission['form_slug'] ?? null);

        if (! $form) {
            return redirect(cp_route('riffraff.index'))->with('error', 'That form no longer exists, so the submission could not be released.');
        }

        (new Submission)->form($form)->data(collect($submission['data'] ?? []))->save();

        $manager->disk()->delete($path);

        return redirect(cp_route('riffraff.index'))->with('success', 'Submission released.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $manager = new Manager;
        $path = self::STORAGE_PATH . '/' . $id . '.yaml';

        if ($manager->disk()->exists($path)) {
            $manager->disk()->delete($path);
        }

        return redirect(cp_route('riffraff.index'))->with('success', 'Submission deleted.');
    }

    public function destroyAll(): RedirectResponse
    {
        $manager = new Manager;

        foreach ($manager->disk()->getFiles(self::STORAGE_PATH) as $path) {
            $manager->disk()->delete($path);
        }

        return redirect(cp_route('riffraff.index'))->with('success', 'All held submissions deleted.');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function loadSubmissions(Manager $manager): Collection
    {
        if (! $manager->disk()->exists(self::STORAGE_PATH)) {
            $manager->disk()->makeDirectory(self::STORAGE_PATH);
        }

        return $manager->disk()->getFiles(self::STORAGE_PATH)
            ->filter(fn (string $path): bool => Str::endsWith($path, '.yaml'))
            ->map(function (string $path) use ($manager): array {
                $data = YAML::parse((string) $manager->disk()->get($path));

                return $this->presentSubmission($data, $manager->disk()->lastModified($path));
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function presentSubmission(array $data, int $fileTimestamp): array
    {
        $score = (int) ($data['spam_score'] ?? 0);
        $threshold = (int) ($data['threshold'] ?? 0);
        $flaggedAt = $this->resolveFlaggedAt($data, $fileTimestamp);

        return [
            'id' => (string) ($data['id'] ?? ''),
            'data' => (array) ($data['data'] ?? []),
            'spam_score' => $score,
            'threshold' => $threshold,
            'reasons' => $this->presentReasons($data['reasons'] ?? []),
            'form_slug' => (string) ($data['form_slug'] ?? ''),
            'is_spam' => (bool) ($data['is_spam'] ?? true),
            'preview' => TemplateGuard::breakMustaches($this->buildPreview($data['data'] ?? null)),
            'score_view' => $this->buildScoreView($score, $threshold),
            'flagged_at_human' => $flaggedAt->diffForHumans(),
            'flagged_at_formatted' => $flaggedAt->format('j M Y, g:ia'),
            'flagged_at_timestamp' => $flaggedAt->getTimestamp(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveFlaggedAt(array $data, int $fileTimestamp): Carbon
    {
        if (is_string($data['flagged_at'] ?? null)) {
            try {
                return Carbon::parse($data['flagged_at']);
            } catch (\Exception) {
            }
        }

        return Carbon::createFromTimestamp($fileTimestamp);
    }

    /**
     * @return array{score: int, threshold: int, percent: int, diff: int, is_over: bool, margin_label: string}
     */
    private function buildScoreView(int $score, int $threshold): array
    {
        $safeThreshold = max($threshold, 1);
        $percent = (int) round(min(100, max(0, $score / $safeThreshold * 100)));
        $diff = $score - $threshold;

        $marginLabel = match (true) {
            $diff > 0 => $diff . ' over the threshold',
            $diff < 0 => abs($diff) . ' under the threshold',
            default => 'exactly on the threshold',
        };

        return [
            'score' => $score,
            'threshold' => $threshold,
            'percent' => $percent,
            'diff' => $diff,
            'is_over' => $diff > 0,
            'margin_label' => $marginLabel,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function matchesSearch(array $item, string $search): bool
    {
        $needle = Str::lower($search);

        if (Str::contains(Str::lower($item['form_slug']), $needle)) {
            return true;
        }

        if (Str::contains(Str::lower($item['preview']), $needle)) {
            return true;
        }

        foreach (Arr::flatten($item['data']) as $value) {
            if (is_string($value) && Str::contains(Str::lower($value), $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function presentReasons(mixed $reasons): array
    {
        return collect((array) $reasons)
            ->map(fn (mixed $reason): string => TemplateGuard::breakMustaches((string) $reason))
            ->values()
            ->all();
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

        return mb_substr($source, 0, 100) . (mb_strlen($source) > 100 ? '...' : '');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{label: string, value: string, is_empty: bool}>
     */
    private function humaniseFields(array $data): array
    {
        return collect($data)
            ->map(fn (mixed $value, string $handle): array => [
                'label' => (string) Str::of($handle)->headline(),
                'value' => TemplateGuard::breakMustaches($this->displayValue($value)),
                'is_empty' => $this->isEmptyValue($value),
            ])
            ->values()
            ->all();
    }

    private function displayValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $item): string => is_scalar($item) ? (string) $item : (json_encode($item) ?: ''))
                ->implode(', ');
        }

        return (string) ($value ?? '');
    }

    private function isEmptyValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * @return array<string, mixed>|null
     */
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
}
