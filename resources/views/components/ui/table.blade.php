@props([
    'tableClass' => '',
    'wrapClass' => '',
    'compact' => false,
    'wrapped' => true,
])

@php
    $classes = trim('ui-table'.($compact ? ' ui-table--compact' : '').' '.$tableClass);
@endphp

@if($wrapped)
    <div {{ $attributes->class(['table-wrap', 'ui-table-shell', $wrapClass]) }}>
        <table class="{{ $classes }}">
            @isset($head)
                <thead>{{ $head }}</thead>
            @endisset
            <tbody>{{ $slot }}</tbody>
        </table>
    </div>
@else
    <table {{ $attributes->class([$classes]) }}>
        @isset($head)
            <thead>{{ $head }}</thead>
        @endisset
        <tbody>{{ $slot }}</tbody>
    </table>
@endif
