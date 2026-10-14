@props([
    'label' => null,
    'name' => null,
    'value' => null,
    'id' => null,
    'error' => null,
])

@php
    $fieldId = $id ?: $name;
    $textareaClasses = trim('ui-textarea'.($error ? ' is-error' : ''));
@endphp

<label class="ui-field" @if($fieldId) for="{{ $fieldId }}" @endif>
    @if($label)
        <span class="ui-label">{{ $label }}</span>
    @endif
    <textarea
        @if($name) name="{{ $name }}" @endif
        @if($fieldId) id="{{ $fieldId }}" @endif
        {{ $attributes->merge(['class' => $textareaClasses]) }}
    >{{ old($name ?? '', $value) }}</textarea>
    @if($error)
        <span class="ui-error-text">{{ $error }}</span>
    @endif
</label>
