@extends('statamic::layout')

@section('content')
    <div class="rr:pt-6 rr:pb-12 rr:max-w-3xl">
        <a href="{{ cp_route('riffraff.index') }}"
           class="rr:text-sm rr:text-gray-500 rr:hover:text-gray-700 rr:inline-flex rr:items-center rr:gap-1 rr:mb-3">
            &larr; Back to held submissions
        </a>

        <h1 class="rr:text-xl rr:font-semibold rr:text-gray-900 rr:mb-1">Review submission</h1>
        <p class="rr:text-sm rr:text-gray-600 rr:mb-5">
            Submitted to
            <a class="rr:text-blue-600 rr:underline rr:hover:text-blue-700" href="{{ cp_route('forms.show', $submission['form_slug']) }}">{{ $submission['form_slug'] }}</a>,
            {{ $submission['flagged_at_human'] }} ({{ $submission['flagged_at_formatted'] }}).
        </p>

        <div class="rr:bg-white rr:border rr:border-gray-200 rr:rounded-md rr:shadow-sm rr:p-4 rr:mb-4">
            <div class="rr:flex rr:items-center rr:justify-between rr:gap-4 rr:flex-wrap">
                <div>
                    <p class="rr:text-xs rr:font-medium rr:text-gray-500 rr:uppercase rr:tracking-wide">Spam score</p>
                    <p class="rr:text-2xl rr:font-semibold {{ $submission['score_view']['is_over'] ? 'rr:text-red-600' : 'rr:text-amber-600' }}">
                        {{ $submission['score_view']['score'] }} <span class="rr:text-base rr:text-gray-400 rr:font-normal">/ {{ $submission['score_view']['threshold'] }} threshold</span>
                    </p>
                </div>
                <p class="rr:text-sm rr:text-gray-600">{{ ucfirst($submission['score_view']['margin_label']) }}</p>
            </div>

            <div class="rr:mt-3 rr:relative rr:h-2 rr:w-full rr:bg-gray-100 rr:rounded-full rr:overflow-hidden">
                <div class="rr:h-full rr:rounded-full {{ $submission['score_view']['is_over'] ? 'rr:bg-red-500' : 'rr:bg-amber-500' }}"
                     style="width: {{ $submission['score_view']['percent'] }}%"></div>
                <div class="rr:absolute rr:top-0 rr:bottom-0 rr:border-l rr:border-gray-400" style="left: 100%"></div>
            </div>
            <p class="rr:text-xs rr:text-gray-400 rr:mt-1">The line marks the threshold. The bar is capped at 100% even when the score runs over it.</p>
        </div>

        <div class="rr:bg-white rr:border rr:border-gray-200 rr:rounded-md rr:shadow-sm rr:p-4 rr:mb-4">
            <h2 class="rr:text-sm rr:font-semibold rr:text-gray-900 rr:mb-2">Why this was flagged</h2>
            @if (count($submission['reasons']))
                <ul class="rr:list-disc rr:list-inside rr:text-sm rr:text-gray-700 rr:space-y-1">
                    @foreach ($submission['reasons'] as $reason)
                        <li>{{ $reason }}</li>
                    @endforeach
                </ul>
            @else
                <p class="rr:text-sm rr:text-gray-500">Riff-Raff did not record specific reasons for this submission.</p>
            @endif
        </div>

        <div class="rr:bg-white rr:border rr:border-gray-200 rr:rounded-md rr:shadow-sm rr:p-4 rr:mb-4">
            <h2 class="rr:text-sm rr:font-semibold rr:text-gray-900 rr:mb-3">Submitted fields</h2>
            <dl class="rr:divide-y rr:divide-gray-100">
                @foreach ($fields as $field)
                    <div class="rr:py-2 rr:grid rr:grid-cols-3 rr:gap-4">
                        <dt class="rr:text-sm rr:font-medium rr:text-gray-600">{{ $field['label'] }}</dt>
                        <dd class="rr:col-span-2 rr:text-sm rr:text-gray-900 rr:whitespace-pre-wrap rr:break-words">
                            @if ($field['is_empty'])
                                <span class="rr:text-gray-400">Empty</span>
                            @else
                                {{ $field['value'] }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>

            <details class="rr:mt-4">
                <summary class="rr:cursor-pointer rr:text-xs rr:font-medium rr:text-gray-500 rr:hover:text-gray-700">
                    View raw submission data
                </summary>
                <pre class="rr:bg-gray-50 rr:border rr:border-gray-200 rr:text-gray-700 rr:text-xs rr:p-3 rr:rounded rr:overflow-auto rr:mt-2"><code>{{ $raw }}</code></pre>
            </details>
        </div>

        <div class="rr:flex rr:items-center rr:gap-3">
            <form method="POST" action="{{ cp_route('riffraff.store', ['id' => $submission['id']]) }}">
                @csrf
                <button type="submit"
                        class="rr:inline-flex rr:items-center rr:px-4 rr:py-2 rr:text-sm rr:font-semibold rr:rounded-md rr:border rr:border-transparent rr:text-white rr:bg-green-600 rr:hover:bg-green-700">
                    Release as genuine
                </button>
            </form>

            <details class="rr:relative">
                <summary class="rr:list-none rr:cursor-pointer rr:inline-flex rr:items-center rr:px-4 rr:py-2 rr:text-sm rr:font-medium rr:rounded-md rr:border rr:border-gray-300 rr:text-gray-600 rr:bg-white rr:hover:bg-gray-50">
                    Delete&hellip;
                </summary>
                <div class="rr:absolute rr:left-0 rr:z-10 rr:mt-2 rr:w-64 rr:bg-white rr:border rr:border-gray-200 rr:rounded-md rr:shadow-lg rr:p-4">
                    <p class="rr:text-sm rr:text-gray-700 rr:mb-3">This permanently deletes the submission. This cannot be undone.</p>
                    <form method="POST" action="{{ cp_route('riffraff.destroy', ['id' => $submission['id']]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="rr:inline-flex rr:items-center rr:px-3 rr:py-1.5 rr:text-sm rr:font-medium rr:rounded-md rr:border rr:border-red-300 rr:text-red-600 rr:bg-white rr:hover:bg-red-50">
                            Confirm delete
                        </button>
                    </form>
                </div>
            </details>
        </div>
    </div>
@endsection
