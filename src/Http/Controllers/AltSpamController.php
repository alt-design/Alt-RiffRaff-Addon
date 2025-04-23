<?php

declare(strict_types=1);

namespace AltDesign\SpamAddon\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
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

        if (! $manager->disk()->exists('content/alt-spam')) {
            $manager->disk()->makeDirectory('content/alt-spam');
        }

        $allSubmissions = File::allFiles(app_path() . '/../content/alt-spam');
        $allSubmissions = collect($allSubmissions)->sortByDesc(function ($file) {
            return $file->getCTime();
        });

        foreach ($allSubmissions as $submission) {
            // event submission form handle
            $data = YAML::parse(File::get($submission));
            $this->data[] = $data;
        }

        $blueprint = with(new BlueprintRepository)
            ->setDirectory(
                __DIR__ . '/../../../resources/blueprints'
            )->find('altkismet');

        $fields = $blueprint->fields()->addValues($this->data);

        $fields = $fields->preProcess();

        return view('spam-addon::index', [
            'blueprint' => $blueprint->toPublishArray(),
            'values' => $fields->values(),
            'meta' => $fields->meta(),
            'data' => $this->data,
        ]);
    }

    public function destroy(string $id): Response
    {
        $manager = new Manager;

        if ($manager->disk()->exists('content/alt-spam/' . $id . '.yaml')) {
            $manager->disk()->delete('content/alt-spam/' . $id . '.yaml');
        }

        return response('', 204);
    }

    public function store(string $id): Response
    {
        $manager = new Manager;

        if (! $manager->disk()->exists('content/alt-spam')) {
            $manager->disk()->makeDirectory('content/alt-spam');
        }

        $submission = File::get(app_path() . '/../content/alt-spam/' . $id . '.yaml');
        $submission = YAML::parse($submission);

        $form = Form::find($submission['form_slug']);

        $data = collect($submission['data']);

        $submission = new Submission;
        $submission->form($form)->data($data)->save();

        if ($manager->disk()->exists('content/alt-spam/' . $id . '.yaml')) {
            $manager->disk()->delete('content/alt-spam/' . $id . '.yaml');
        }

        return response('', 204);
    }

    public function show(string $id)
    {
        $manager = new Manager;

        if (! $manager->disk()->exists('content/alt-spam')) {
            $manager->disk()->makeDirectory('content/alt-spam');
        }

        $submission = File::get(app_path() . '/../content/alt-spam/' . $id . '.yaml');
        $submission = YAML::parse($submission);

        return view('spam-addon::show', [
            'id' => $submission['id'],
            'submission' => $submission,
            'form' => Form::find($submission['form_slug']),
            'data' => collect($submission['data']),
            'score' => (int) $submission['spam_score'],
            'threshold' => (int) $submission['threshold'],
        ]);
    }
}
