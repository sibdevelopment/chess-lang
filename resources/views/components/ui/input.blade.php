@props([
    'label' => null,
    'name' => null,
    'value' => null,
    'type' => 'text',
    'id' => null,
    'error' => null,
])

@php
    $fieldId = $id ?: $name;
    $inputClasses = trim('ui-input'.($error ? ' is-error' : ''));
@endphp

<label class="ui-field" @if($fieldId) for="{{ $fieldId }}" @endif>
    @if($label)
        <span class="ui-label">{{ $label }}</span>
    @endif
    <input
        type="{{ $type }}"
        @if($name) name="{{ $name }}" @endif
        @if($fieldId) id="{{ $fieldId }}" @endif
        value="{{ old($name ?? '', $value) }}"
        {{ $attributes->merge(['class' => $inputClasses]) }}
    >
    @if($error)
        <span class="ui-error-text">{{ $error }}</span>
    @endif
</label>
