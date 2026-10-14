@props([
    'id' => null,
    'visible' => false,
    'maxWidth' => '560px',
    'modalClass' => '',
    'modalStyle' => '',
    'backdropClose' => true,
    'bodyClass' => '',
])

@php
    $backdropStyle = trim((string) $attributes->get('style'));
    $backdropStyle = ($backdropStyle !== '' ? rtrim($backdropStyle, ';').' ; ' : '').'display:'.($visible ? 'flex' : 'none');
    $dialogStyle = trim('max-width:'.$maxWidth.'; '.$modalStyle);
@endphp

<div
    {{ $attributes->except('style')->merge(['class' => 'modal-bg']) }}
    @if($id) id="{{ $id }}" @endif
    style="{{ $backdropStyle }}"
    @if($backdropClose && !$attributes->has('onclick')) onclick="if(event.target===this)this.style.display='none'" @endif
>
    <div class="modal {{ trim($modalClass) }}" style="{{ $dialogStyle }}">
        @isset($header)
            <div class="ui-modal-header">{{ $header }}</div>
        @endisset

        <div class="ui-modal-body {{ trim($bodyClass) }}">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="ui-modal-footer">{{ $footer }}</div>
        @endisset
    </div>
</div>
