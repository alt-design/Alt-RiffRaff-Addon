@extends('statamic::layout')

@section('content')
    <div class="rr:pt-6 rr:pb-12">
        <div class="rr:flex rr:items-start rr:justify-between rr:gap-4 rr:mb-1">
            <div>
                <h1 class="rr:text-xl rr:font-semibold rr:text-gray-900">Held spam submissions</h1>
                <p class="rr:text-sm rr:text-gray-600 rr:mt-1">
                    Riff-Raff flagged these form submissions before they reached your entries. Check each one and
                    release it if it was a genuine enquiry.
                </p>
            </div>

            @if ($totalCount > 0)
                <details class="rr:relative">
                    <summary class="rr:list-none rr:cursor-pointer rr:inline-flex rr:items-center rr:px-3 rr:py-1.5 rr:text-sm rr:font-medium rr:rounded-md rr:border rr:border-gray-300 rr:text-gray-600 rr:bg-white rr:hover:bg-gray-50">
                        Delete all&hellip;
                    </summary>
                    <div class="rr:absolute rr:right-0 rr:z-10 rr:mt-2 rr:w-72 rr:bg-white rr:border rr:border-gray-200 rr:rounded-md rr:shadow-lg rr:p-4">
                        <p class="rr:text-sm rr:text-gray-700 rr:mb-3">
                            This permanently deletes all {{ $totalCount }} held submission{{ $totalCount === 1 ? '' : 's' }},
                            including any that may have been genuine enquiries.
                        </p>
                        <form method="POST" action="{{ cp_route('riffraff.destroyAll') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="rr:inline-flex rr:items-center rr:px-3 rr:py-1.5 rr:text-sm rr:font-medium rr:rounded-md rr:border rr:border-red-300 rr:text-red-600 rr:bg-white rr:hover:bg-red-50">
                                Confirm: delete all {{ $totalCount }}
                            </button>
                        </form>
                    </div>
                </details>
            @endif
        </div>

        @if ($usageView)
            <div class="rr:bg-white rr:border rr:border-gray-200 rr:rounded-md rr:shadow-sm rr:p-4 rr:mt-5">
                <div class="rr:flex rr:items-baseline rr:justify-between rr:flex-wrap rr:gap-2">
                    <div>
                        <h2 class="rr:text-sm rr:font-semibold rr:text-gray-900">Riff-Raff usage this month</h2>
                        @if ($usageView['plan_name'])
                            <p class="rr:text-xs rr:text-gray-600 rr:mt-0.5">
                                Plan: {{ $usageView['plan_name'] }}@if ($usageView['is_exempt']) &middot; Exempt @endif
                            </p>
                        @endif
                    </div>
                    <div class="rr:text-right">
                        <p class="rr:font-semibold {{ $usageView['text_color_class'] }}">{{ $usageView['headline'] }}</p>
                        @if ($usageView['resets_at_formatted'])
                            <p class="rr:text-xs rr:text-gray-600 rr:mt-0.5">Resets {{ $usageView['resets_at_formatted'] }}</p>
                        @endif
                    </div>
                </div>

                @unless ($usageView['is_unlimited'])
                    <div class="rr:mt-3">
                        <div class="rr:h-1.5 rr:w-full rr:bg-gray-100 rr:rounded-full rr:overflow-hidden">
                            <div class="rr:h-full rr:rounded-full {{ $usageView['bar_color_class'] }}"
                                 style="{{ $usageView['bar_width_style'] }}"></div>
                        </div>
                        <p class="rr:text-xs rr:text-gray-600 rr:mt-1">{{ $usageView['percent_used'] }}% used</p>
                    </div>
                @endunless

                @if ($usageView['is_over_quota'])
                    <p class="rr:text-xs rr:text-red-600 rr:mt-2">Quota reached. Further submissions will not be checked until the reset date.</p>
                @elseif ($usageView['is_near_cap'])
                    <p class="rr:text-xs rr:text-orange-600 rr:mt-2">Approaching your monthly limit. Consider upgrading before submissions stop being checked.</p>
                @endif
            </div>
        @endif

        @if (session('success'))
            <div class="rr:bg-green-50 rr:border rr:border-green-200 rr:text-green-800 rr:text-sm rr:rounded-md rr:px-4 rr:py-3 rr:mt-5">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rr:bg-red-50 rr:border rr:border-red-200 rr:text-red-800 rr:text-sm rr:rounded-md rr:px-4 rr:py-3 rr:mt-5">
                {{ session('error') }}
            </div>
        @endif

        @if ($totalCount > 0)
            <form method="GET" action="{{ cp_route('riffraff.index') }}"
                  class="rr:flex rr:flex-wrap rr:items-end rr:gap-3 rr:mt-6">
                <div>
                    <label for="riffraff-search" class="rr:block rr:text-xs rr:font-medium rr:text-gray-600 rr:mb-1">Search</label>
                    <input type="search" id="riffraff-search" name="q" value="{{ $search }}"
                           placeholder="Search submission content"
                           class="rr:w-64 rr:rounded-md rr:border rr:border-gray-300 rr:text-sm rr:px-3 rr:py-1.5">
                </div>

                <div>
                    <label for="riffraff-form" class="rr:block rr:text-xs rr:font-medium rr:text-gray-600 rr:mb-1">Form</label>
                    <select id="riffraff-form" name="form"
                            class="rr:rounded-md rr:border rr:border-gray-300 rr:text-sm rr:px-3 rr:py-1.5">
                        <option value="">All forms</option>
                        @foreach ($formOptions as $option)
                            <option value="{{ $option }}" @selected($formFilter === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>

                <input type="hidden" name="sort" value="{{ $sort }}">

                <button type="submit"
                        class="rr:inline-flex rr:items-center rr:px-3 rr:py-1.5 rr:text-sm rr:font-medium rr:rounded-md rr:border rr:border-gray-300 rr:text-gray-700 rr:bg-white rr:hover:bg-gray-50">
                    Filter
                </button>

                @if ($search !== '' || $formFilter !== '')
                    <a href="{{ cp_route('riffraff.index', ['sort' => $sort]) }}"
                       class="rr:text-sm rr:text-gray-500 rr:hover:text-gray-700 rr:pb-1.5">
                        Clear
                    </a>
                @endif

                <div class="rr:ml-auto rr:flex rr:items-center rr:gap-3 rr:text-sm rr:pb-1.5">
                    <span class="rr:text-gray-500">Sort by</span>
                    <a href="{{ cp_route('riffraff.index', array_filter(['q' => $search, 'form' => $formFilter, 'sort' => 'recent'])) }}"
                       class="{{ $sort === 'recent' ? 'rr:font-semibold rr:text-gray-900' : 'rr:text-blue-600 rr:hover:text-blue-700' }}">
                        Most recent
                    </a>
                    <span class="rr:text-gray-300">&middot;</span>
                    <a href="{{ cp_route('riffraff.index', array_filter(['q' => $search, 'form' => $formFilter, 'sort' => 'score'])) }}"
                       class="{{ $sort === 'score' ? 'rr:font-semibold rr:text-gray-900' : 'rr:text-blue-600 rr:hover:text-blue-700' }}">
                        Furthest over threshold
                    </a>
                </div>
            </form>
        @endif

        <div class="rr:bg-white rr:border rr:border-gray-200 rr:rounded-md rr:shadow-sm rr:overflow-hidden rr:mt-4">
            <table class="rr:w-full rr:text-sm rr:border-collapse">
                <thead class="rr:bg-gray-50 rr:border-b rr:border-gray-200">
                    <tr>
                        <th class="rr:text-left rr:px-4 rr:py-2 rr:font-medium rr:text-gray-600 rr:uppercase rr:text-xs rr:tracking-wider">Score</th>
                        <th class="rr:text-left rr:px-4 rr:py-2 rr:font-medium rr:text-gray-600 rr:uppercase rr:text-xs rr:tracking-wider">Why it was flagged</th>
                        <th class="rr:text-left rr:px-4 rr:py-2 rr:font-medium rr:text-gray-600 rr:uppercase rr:text-xs rr:tracking-wider">Submission</th>
                        <th class="rr:text-left rr:px-4 rr:py-2 rr:font-medium rr:text-gray-600 rr:uppercase rr:text-xs rr:tracking-wider">Form</th>
                        <th class="rr:text-left rr:px-4 rr:py-2 rr:font-medium rr:text-gray-600 rr:uppercase rr:text-xs rr:tracking-wider">Flagged</th>
                        <th class="rr:px-4 rr:py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($submissions as $item)
                        <tr class="rr:border-b rr:border-gray-200 rr:last:border-b-0 rr:align-top">
                            <td class="rr:px-4 rr:py-3 rr:whitespace-nowrap">
                                <span class="rr:inline-flex rr:items-center rr:px-2 rr:py-0.5 rr:rounded rr:text-xs rr:font-semibold {{ $item['score_view']['is_over'] ? 'rr:bg-red-100 rr:text-red-700' : 'rr:bg-amber-100 rr:text-amber-700' }}">
                                    {{ $item['score_view']['score'] }} / {{ $item['score_view']['threshold'] }}
                                </span>
                                <p class="rr:text-xs rr:text-gray-500 rr:mt-1">{{ $item['score_view']['margin_label'] }}</p>
                            </td>
                            <td class="rr:px-4 rr:py-3 rr:text-gray-800 rr:max-w-xs">
                                @if (count($item['reasons']))
                                    <p>{{ $item['reasons'][0] }}</p>
                                    @if (count($item['reasons']) > 1)
                                        <p class="rr:text-xs rr:text-gray-500 rr:mt-0.5">
                                            +{{ count($item['reasons']) - 1 }} more reason{{ count($item['reasons']) - 1 === 1 ? '' : 's' }}
                                        </p>
                                    @endif
                                @else
                                    <span class="rr:text-gray-400">No reasons recorded</span>
                                @endif
                            </td>
                            <td class="rr:px-4 rr:py-3 rr:text-gray-700 rr:max-w-xs">
                                {{ $item['preview'] !== '' ? $item['preview'] : 'No preview available' }}
                            </td>
                            <td class="rr:px-4 rr:py-3">
                                <a class="rr:text-blue-600 rr:underline rr:hover:text-blue-700" href="{{ cp_route('forms.show', $item['form_slug']) }}">
                                    {{ $item['form_slug'] }}
                                </a>
                            </td>
                            <td class="rr:px-4 rr:py-3 rr:text-gray-600 rr:whitespace-nowrap" title="{{ $item['flagged_at_formatted'] }}">
                                {{ $item['flagged_at_human'] }}
                            </td>
                            <td class="rr:px-4 rr:py-3">
                                <div class="rr:flex rr:items-center rr:gap-2 rr:justify-end">
                                    <a href="{{ cp_route('riffraff.show', ['id' => $item['id']]) }}"
                                       class="rr:inline-flex rr:items-center rr:px-3 rr:py-1.5 rr:text-sm rr:font-medium rr:rounded-md rr:border rr:border-gray-300 rr:text-gray-700 rr:bg-white rr:hover:bg-gray-50">
                                        Review
                                    </a>
                                    <form method="POST" action="{{ cp_route('riffraff.store', ['id' => $item['id']]) }}">
                                        @csrf
                                        <button type="submit"
                                                class="rr:inline-flex rr:items-center rr:px-3 rr:py-1.5 rr:text-sm rr:font-semibold rr:rounded-md rr:border rr:border-transparent rr:text-white rr:bg-green-600 rr:hover:bg-green-700">
                                            Release
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="rr:px-4 rr:py-10 rr:text-center">
                                @if ($totalCount === 0)
                                    <p class="rr:text-sm rr:font-medium rr:text-gray-700">Nothing held for review</p>
                                    <p class="rr:text-xs rr:text-gray-500 rr:mt-1">Every form submission is currently reaching your entries as normal.</p>
                                @else
                                    <p class="rr:text-sm rr:font-medium rr:text-gray-700">No submissions match your search</p>
                                    <p class="rr:text-xs rr:text-gray-500 rr:mt-1">Try a different search term, or clear the filters above.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
