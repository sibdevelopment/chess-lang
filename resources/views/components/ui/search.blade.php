@props([
    'name' => 'search',
    'value' => '',
    'placeholder' => 'Search',
    'id' => null,
])

@php
    $fieldId = $id ?: $name;
@endphp

<div class="ui-search">
    <span class="ui-search__icon" aria-hidden="true">
        <svg viewBox="0 0 20 20" fill="none">
            <circle cx="8.75" cy="8.75" r="5.75" stroke="currentColor" stroke-width="1.6" />
            <path d="M13 13L17 17" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
        </svg>
    </span>
    <input
        type="text"
        name="{{ $name }}"
        id="{{ $fieldId }}"
        value="{{ $value }}"
        placeholder="{{ $placeholder }}"
        autocomplete="off"
        {{ $attributes->merge(['class' => 'ui-input ui-search__input']) }}
    >
</div>
