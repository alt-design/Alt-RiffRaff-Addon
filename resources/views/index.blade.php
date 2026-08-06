@extends('statamic::layout')

@section('content')
    <div class="pt-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-xl font-semibold text-gray-900">Suspected Spam Form Submissions</h1>
            <form method="POST" action="{{ cp_route('riffraff.destroy', ['id' => 'all']) }}"
                  onsubmit="return confirm('Are you sure? This will delete ALL suspected spam entries.')">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded border border-red-300 text-red-600 bg-white hover:bg-red-50">
                    Delete All
                </button>
            </form>
        </div>

        <div class="mb-4 text-sm text-gray-700">
            <p>
                Below is a list of form submissions that may be spam. You can choose to
                <span class="text-green-600 font-medium">release</span> them, allowing them to be treated as valid submissions,
                or <span class="text-red-600 font-medium">delete</span> them permanently.
            </p>
        </div>

        @if ($usageView)
            <div class="bg-white border border-gray-200 rounded-md shadow-sm p-4 mb-4">
                <div class="flex items-baseline justify-between flex-wrap gap-2">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Riff-Raff usage this month</h2>
                        @if ($usageView['plan_name'])
                            <p class="text-xs text-gray-600 mt-0.5">
                                Plan: {{ $usageView['plan_name'] }}@if ($usageView['is_exempt']) · Exempt @endif
                            </p>
                        @endif
                    </div>
                    <div class="text-right">
                        <p class="font-semibold {{ $usageView['text_color_class'] }}">{{ $usageView['headline'] }}</p>
                        @if ($usageView['resets_at_formatted'])
                            <p class="text-xs text-gray-600 mt-0.5">Resets {{ $usageView['resets_at_formatted'] }}</p>
                        @endif
                    </div>
                </div>

                @unless ($usageView['is_unlimited'])
                    <div class="mt-3">
                        <div class="h-2 w-full bg-gray-200 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all {{ $usageView['bar_color_class'] }}"
                                 style="{{ $usageView['bar_width_style'] }}"></div>
                        </div>
                        <p class="text-xs text-gray-600 mt-1">{{ $usageView['percent_used'] }}% used</p>
                    </div>
                @endunless

                @if ($usageView['is_over_quota'])
                    <p class="text-xs text-red-600 mt-2">Quota reached. Further submissions will not be checked until the reset date.</p>
                @elseif ($usageView['is_near_cap'])
                    <p class="text-xs text-orange-600 mt-2">Approaching your monthly limit — consider upgrading before submissions are blocked.</p>
                @endif
            </div>
        @endif

        <div class="bg-white border border-gray-200 rounded-md shadow-sm overflow-hidden">
            <table class="w-full text-sm border-collapse">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-4 py-2 font-medium text-gray-600 uppercase text-xs tracking-wider">Form Name</th>
                        <th class="text-left px-4 py-2 font-medium text-gray-600 uppercase text-xs tracking-wider">Spam Score</th>
                        <th class="text-left px-4 py-2 font-medium text-gray-600 uppercase text-xs tracking-wider">Preview</th>
                        <th class="text-left px-4 py-2 font-medium text-gray-600 uppercase text-xs tracking-wider">Form</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($data as $item)
                        <tr class="border-b border-gray-200 last:border-b-0">
                            <td class="px-4 py-3 text-gray-900">{{ $item['data']['name'] ?? 'Unknown' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $item['spam_score'] }} / {{ $item['threshold'] }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $item['preview'] }}</td>
                            <td class="px-4 py-3">
                                <a class="text-blue-600 underline hover:text-blue-700" href="{{ cp_route('forms.show', $item['form_slug']) }}">
                                    {{ $item['form_slug'] }}
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 justify-end">
                                    <a href="{{ cp_route('riffraff.show', ['id' => $item['id']]) }}"
                                       class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded border border-blue-300 text-blue-600 bg-white hover:bg-blue-50">
                                        View
                                    </a>
                                    <form method="POST" action="{{ cp_route('riffraff.store', ['id' => $item['id']]) }}">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded border border-green-300 text-green-600 bg-white hover:bg-green-50">
                                            Release
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ cp_route('riffraff.destroy', ['id' => $item['id']]) }}"
                                          onsubmit="return confirm('Are you sure?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded border border-red-300 text-red-600 bg-white hover:bg-red-50">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">No suspected spam submissions.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
