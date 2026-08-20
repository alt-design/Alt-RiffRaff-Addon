@extends('statamic::layout')

@section('content')
    <div class="rr:pt-6">
        <h1 class="rr:text-xl rr:font-semibold rr:text-gray-900 rr:mb-4">Spam Review Form Submission - {{ $id }}</h1>

        <div class="rr:bg-white rr:border rr:border-gray-200 rr:rounded-md rr:shadow-sm rr:p-4 rr:mb-4 rr:text-sm rr:text-gray-700">
            <p>
                This form submission received a score of
                <span @class([
                    'font-semibold',
                    'text-red-600' => $score > $threshold,
                    'text-gray-900' => $score <= $threshold,
                ])>{{ $score }} / {{ $threshold }}</span>
                and may be considered spam based on its content. You can choose to delete it or mark it as a
                legitimate submission below by choosing to "release" it.
            </p>
        </div>

        <pre class="rr:bg-gray-900 rr:text-gray-100 rr:text-xs rr:p-4 rr:rounded-md rr:overflow-auto rr:mb-4"><code>{{ json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>

        <div class="rr:flex rr:items-center rr:gap-2">
            <a href="{{ cp_route('riffraff.index') }}"
               class="rr:inline-flex rr:items-center rr:px-3 rr:py-1.5 rr:text-sm rr:font-medium rr:rounded rr:border rr:border-gray-300 rr:text-gray-700 rr:bg-white rr:hover:bg-gray-50">
                Back
            </a>
            <form method="POST" action="{{ cp_route('riffraff.store', ['id' => $id]) }}">
                @csrf
                <button type="submit"
                        class="rr:inline-flex rr:items-center rr:px-3 rr:py-1.5 rr:text-sm rr:font-medium rr:rounded rr:border rr:border-green-300 rr:text-green-600 rr:bg-white rr:hover:bg-green-50">
                    Release
                </button>
            </form>
            <form method="POST" action="{{ cp_route('riffraff.destroy', ['id' => $id]) }}"
                  onsubmit="return confirm('Are you sure?')">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="rr:inline-flex rr:items-center rr:px-3 rr:py-1.5 rr:text-sm rr:font-medium rr:rounded rr:border rr:border-red-300 rr:text-red-600 rr:bg-white rr:hover:bg-red-50">
                    Delete
                </button>
            </form>
        </div>
    </div>
@endsection
