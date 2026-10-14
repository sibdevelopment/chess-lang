@props([
    'title',
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'ui-page-header']) }}>
    <div class="ui-page-header__copy">
        <h1 class="ui-page-title">{{ $title }}</h1>
        @if($subtitle)
            <p class="ui-body-text">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="ui-page-header__actions">{{ $actions }}</div>
    @endisset
</div>
