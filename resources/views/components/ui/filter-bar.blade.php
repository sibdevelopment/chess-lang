<div {{ $attributes->merge(['class' => 'ui-filter-bar']) }}>
    <div class="ui-filter-bar__main">
        {{ $slot }}
    </div>
    @isset($actions)
        <div class="ui-filter-bar__actions">{{ $actions }}</div>
    @endisset
</div>
