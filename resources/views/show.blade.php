@extends('statamic::layout')

@section('content')
    <div class="pt-6">
        <h1 class="text-xl font-semibold text-gray-900 mb-4">Spam Review Form Submission - {{ $id }}</h1>

        <div class="bg-white border border-gray-200 rounded-md shadow-sm p-4 mb-4 text-sm text-gray-700">
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

        <pre class="bg-gray-900 text-gray-100 text-xs p-4 rounded-md overflow-auto mb-4"><code>{{ json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>

        <div class="flex items-center gap-2">
            <a href="{{ cp_route('riffraff.index') }}"
               class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded border border-gray-300 text-gray-700 bg-white hover:bg-gray-50">
                Back
            </a>
            <form method="POST" action="{{ cp_route('riffraff.store', ['id' => $id]) }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded border border-green-300 text-green-600 bg-white hover:bg-green-50">
                    Release
                </button>
            </form>
            <form method="POST" action="{{ cp_route('riffraff.destroy', ['id' => $id]) }}"
                  onsubmit="return confirm('Are you sure?')">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded border border-red-300 text-red-600 bg-white hover:bg-red-50">
                    Delete
                </button>
            </form>
        </div>
    </div>
@endsection
