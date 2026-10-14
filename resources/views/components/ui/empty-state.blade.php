@props([
    'title' => 'No records found',
    'description' => null,
    'actionLabel' => null,
    'actionHref' => null,
])

<div {{ $attributes->merge(['class' => 'ui-empty-state']) }}>
    <div class="ui-empty-state__title">{{ $title }}</div>
    @if($description)
        <div class="ui-empty-state__description">{{ $description }}</div>
    @endif
    @if($actionLabel && $actionHref)
        <x-ui.button :href="$actionHref" variant="secondary">{{ $actionLabel }}</x-ui.button>
    @endif
</div>
