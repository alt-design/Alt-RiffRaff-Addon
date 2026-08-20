@extends('statamic::layout')

@section('content')
    <div class="rr:pt-6">
        <div class="rr:flex rr:items-center rr:justify-between rr:mb-4">
            <h1 class="rr:text-xl rr:font-semibold rr:text-gray-900">Suspected Spam Form Submissions</h1>
            <form method="POST" action="{{ cp_route('riffraff.destroy', ['id' => 'all']) }}"
                  onsubmit="return confirm('Are you sure? This will delete ALL suspected spam entries.')">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="rr:inline-flex rr:items-center rr:px-3 rr:py-1.5 rr:text-sm rr:font-medium rr:rounded rr:border rr:border-red-300 rr:text-red-600 rr:bg-white rr:hover:bg-red-50">
                    Delete All
                </button>
            </form>
        </div>

        <div class="rr:mb-4 rr:text-sm rr:text-gray-700">
            <p>
                Below is a list of form submissions that may be spam. You can choose to
                <span class="rr:text-green-600 rr:font-medium">release</span> them, allowing them to be treated as valid submissions,
                or <span class="rr:text-red-600 rr:font-medium">delete</span> them permanently.
            </p>
        </div>

        @if ($usageView)
            <div class="rr:bg-white rr:border rr:border-gray-200 rr:rounded-md rr:shadow-sm rr:p-4 rr:mb-4">
                <div class="rr:flex rr:items-baseline rr:justify-between rr:flex-wrap rr:gap-2">
                    <div>
                        <h2 class="rr:text-base rr:font-semibold rr:text-gray-900">Riff-Raff usage this month</h2>
                        @if ($usageView['plan_name'])
                            <p class="rr:text-xs rr:text-gray-600 rr:mt-0.5">
                                Plan: {{ $usageView['plan_name'] }}@if ($usageView['is_exempt']) · Exempt @endif
                            </p>
                        @endif
                    </div>
                    <div class="rr:text-right">
                        <p class="rr:font-semibold {{ rr:$usageView['text_color_class'] }}">{{ $usageView['headline'] }}</p>
                        @if ($usageView['resets_at_formatted'])
                            <p class="rr:text-xs rr:text-gray-600 rr:mt-0.5">Resets {{ $usageView['resets_at_formatted'] }}</p>
                        @endif
                    </div>
                </div>

                @unless ($usageView['is_unlimited'])
                    <div class="rr:mt-3">
                        <div class="rr:h-2 rr:w-full rr:bg-gray-200 rr:rounded-full rr:overflow-hidden">
                            <div class="rr:h-full rr:rounded-full rr:transition-all {{ rr:$usageView['bar_color_class'] }}"
                                 style="{{ $usageView['bar_width_style'] }}"></div>
                        </div>
                        <p class="rr:text-xs rr:text-gray-600 rr:mt-1">{{ $usageView['percent_used'] }}% used</p>
                    </div>
                @endunless

                @if ($usageView['is_over_quota'])
                    <p class="rr:text-xs rr:text-red-600 rr:mt-2">Quota reached. Further submissions will not be checked until the reset date.</p>
                @elseif ($usageView['is_near_cap'])
                    <p class="rr:text-xs rr:text-orange-600 rr:mt-2">Approaching your monthly limit — consider upgrading before submissions are blocked.</p>
                @endif
            </div>
        @endif

        <div class="rr:bg-white rr:border rr:border-gray-200 rr:rounded-md rr:shadow-sm rr:overflow-hidden">
            <table class="rr:w-full rr:text-sm rr:border-collapse">
                <thead class="rr:bg-gray-50 rr:border-b rr:border-gray-200">
                    <tr>
                        <th class="rr:text-left rr:px-4 rr:py-2 rr:font-medium rr:text-gray-600 rr:uppercase rr:text-xs rr:tracking-wider">Form Name</th>
                        <th class="rr:text-left rr:px-4 rr:py-2 rr:font-medium rr:text-gray-600 rr:uppercase rr:text-xs rr:tracking-wider">Spam Score</th>
                        <th class="rr:text-left rr:px-4 rr:py-2 rr:font-medium rr:text-gray-600 rr:uppercase rr:text-xs rr:tracking-wider">Preview</th>
                        <th class="rr:text-left rr:px-4 rr:py-2 rr:font-medium rr:text-gray-600 rr:uppercase rr:text-xs rr:tracking-wider">Form</th>
                        <th class="rr:px-4 rr:py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($data as $item)
                        <tr class="rr:border-b rr:border-gray-200 rr:last:border-b-0">
                            <td class="rr:px-4 rr:py-3 rr:text-gray-900">{{ $item['data']['name'] ?? 'Unknown' }}</td>
                            <td class="rr:px-4 rr:py-3 rr:text-gray-700">{{ $item['spam_score'] }} / {{ $item['threshold'] }}</td>
                            <td class="rr:px-4 rr:py-3 rr:text-gray-700">{{ $item['preview'] }}</td>
                            <td class="rr:px-4 rr:py-3">
                                <a class="rr:text-blue-600 rr:underline rr:hover:text-blue-700" href="{{ cp_route('forms.show', $item['form_slug']) }}">
                                    {{ $item['form_slug'] }}
                                </a>
                            </td>
                            <td class="rr:px-4 rr:py-3">
                                <div class="rr:flex rr:items-center rr:gap-2 rr:justify-end">
                                    <a href="{{ cp_route('riffraff.show', ['id' => $item['id']]) }}"
                                       class="rr:inline-flex rr:items-center rr:px-3 rr:py-1.5 rr:text-sm rr:font-medium rr:rounded rr:border rr:border-blue-300 rr:text-blue-600 rr:bg-white rr:hover:bg-blue-50">
                                        View
                                    </a>
                                    <form method="POST" action="{{ cp_route('riffraff.store', ['id' => $item['id']]) }}">
                                        @csrf
                                        <button type="submit"
                                                class="rr:inline-flex rr:items-center rr:px-3 rr:py-1.5 rr:text-sm rr:font-medium rr:rounded rr:border rr:border-green-300 rr:text-green-600 rr:bg-white rr:hover:bg-green-50">
                                            Release
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ cp_route('riffraff.destroy', ['id' => $item['id']]) }}"
                                          onsubmit="return confirm('Are you sure?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="rr:inline-flex rr:items-center rr:px-3 rr:py-1.5 rr:text-sm rr:font-medium rr:rounded rr:border rr:border-red-300 rr:text-red-600 rr:bg-white rr:hover:bg-red-50">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="rr:px-4 rr:py-6 rr:text-center rr:text-sm rr:text-gray-500">No suspected spam submissions.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
