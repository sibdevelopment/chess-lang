@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'error' => null,
])

@php
    $fieldId = $id ?: $name;
    $selectClasses = trim('ui-select'.($error ? ' is-error' : ''));
@endphp

<label class="ui-field" @if($fieldId) for="{{ $fieldId }}" @endif>
    @if($label)
        <span class="ui-label">{{ $label }}</span>
    @endif
    <select
        @if($name) name="{{ $name }}" @endif
        @if($fieldId) id="{{ $fieldId }}" @endif
        {{ $attributes->merge(['class' => $selectClasses]) }}
    >
        {{ $slot }}
    </select>
    @if($error)
        <span class="ui-error-text">{{ $error }}</span>
    @endif
</label>
