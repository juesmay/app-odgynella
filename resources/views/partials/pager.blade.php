@if ($paginator->hasPages())
    <nav class="row" style="justify-content:space-between" aria-label="Páginas">
        @if ($paginator->onFirstPage())
            <span class="btn sm" aria-disabled="true" style="opacity:.5">‹ Anterior</span>
        @else
            <a class="btn sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Anterior</a>
        @endif
        <span class="small muted">Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
            <a class="btn sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente ›</a>
        @else
            <span class="btn sm" aria-disabled="true" style="opacity:.5">Siguiente ›</span>
        @endif
    </nav>
@endif
