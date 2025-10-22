@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-between">
        <div class="flex justify-between flex-1 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="btn btn-disabled">Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-primary">Previous</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-primary">Next</a>
            @else
                <span class="btn btn-disabled">Next</span>
            @endif
        </div>

        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-center">
            <div>
                <div class="join">
                    {{-- Previous Page Link --}}
                    @if ($paginator->onFirstPage())
                        <button class="join-item btn btn-disabled" aria-disabled="true">
                            <span aria-hidden="true">&laquo;</span>
                        </button>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="join-item btn">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($elements as $element)
                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                            <button class="join-item btn btn-disabled" aria-disabled="true">
                                <span>{{ $element }}</span>
                            </button>
                        @endif

                        {{-- Array Of Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <button class="join-item btn" style="background-color: var(--color-primary); color: var(--color-primary-content); pointer-events: none;" aria-current="page">
                                        <span>{{ $page }}</span>
                                    </button>
                                @else
                                    <a href="{{ $url }}" class="join-item btn">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="join-item btn">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    @else
                        <button class="join-item btn btn-disabled" aria-disabled="true">
                            <span aria-hidden="true">&raquo;</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </nav>
@endif