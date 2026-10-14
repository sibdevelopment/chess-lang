@props([
    'variant' => null,
    'status' => null,
    'label' => null,
])

@php
    $resolved = $variant;
    if (! $resolved && $status) {
        $resolved = str_replace('_', '-', strtolower($status));
    }

    $resolved = $resolved ?: 'secondary';
    $content = $label ?? $slot;
@endphp

<span {{ $attributes->merge(['class' => 'ui-badge ui-badge--'.$resolved]) }}>
    {{ $content }}
</span>
