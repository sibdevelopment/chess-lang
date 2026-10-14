@props([
    'items' => [],
    'ariaLabel' => 'Tabs',
])

<nav {{ $attributes->merge(['class' => 'ui-tabs']) }} aria-label="{{ $ariaLabel }}">
    @foreach($items as $item)
        @php
            $active = (bool) ($item['active'] ?? false);
            $tag = isset($item['href']) ? 'a' : 'button';
            $count = $item['count'] ?? null;
        @endphp
        @if($tag === 'a')
            <a href="{{ $item['href'] }}" class="ui-tab {{ $active ? 'is-active' : '' }}">
                <span>{{ $item['label'] }}</span>
                @if($count !== null)
                    <span class="ui-tab__count">{{ $count }}</span>
                @endif
            </a>
        @else
            <button type="{{ $item['type'] ?? 'button' }}" class="ui-tab {{ $active ? 'is-active' : '' }}">
                <span>{{ $item['label'] }}</span>
                @if($count !== null)
                    <span class="ui-tab__count">{{ $count }}</span>
                @endif
            </button>
        @endif
    @endforeach
</nav>
