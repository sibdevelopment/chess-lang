@if ($paginator->total() > 0)
  <nav class="ui-pagination" aria-label="Pagination">
    <span class="ui-pagination__summary">
      Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
    </span>

    <div class="ui-pagination__pages">
      @if ($paginator->onFirstPage())
        <span class="ui-pagination-link is-disabled" aria-disabled="true" aria-label="Previous page">
          <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M12.5 5L7.5 10L12.5 15" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
        </span>
      @else
        <a class="ui-pagination-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">
          <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M12.5 5L7.5 10L12.5 15" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
        </a>
      @endif

      @foreach ($elements as $element)
        @if (is_string($element))
          <span class="ui-pagination-gap">{{ $element }}</span>
        @endif

        @if (is_array($element))
          @foreach ($element as $page => $url)
            @if ($page == $paginator->currentPage())
              <span class="ui-pagination-link is-active" aria-current="page">{{ $page }}</span>
            @else
              <a class="ui-pagination-link" href="{{ $url }}">{{ $page }}</a>
            @endif
          @endforeach
        @endif
      @endforeach

      @if ($paginator->hasMorePages())
        <a class="ui-pagination-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">
          <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M7.5 5L12.5 10L7.5 15" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
        </a>
      @else
        <span class="ui-pagination-link is-disabled" aria-disabled="true" aria-label="Next page">
          <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M7.5 5L12.5 10L7.5 15" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
        </span>
      @endif
    </div>
  </nav>
@endif
