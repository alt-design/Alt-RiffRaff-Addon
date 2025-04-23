@extends('statamic::layout')

@section('content')
    <spam-review-show
            title="Spam Review"
            :id="{{ $id }}"
            :item="{{ json_encode($data) }}"
            :score="{{ $score }}"
            :threshold="{{ $threshold }}"
    ></spam-review-show>
@endsection
