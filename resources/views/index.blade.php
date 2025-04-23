@extends('statamic::layout')

@section('content')
    <spam-review-index title="Spam Reviews" :items="{{ json_encode($data) }}"></spam-review-index>
@endsection
