@props(['icon' => 'o-squares-2x2', 'title', 'subtitle' => null])

<div class="flex items-center gap-3 mb-6">
    <span class="inline-flex items-center justify-center h-10 w-10 rounded-brand-sm bg-azul/10 text-azul shrink-0">
        {{ svg('heroicon-'.$icon, 'h-5 w-5') }}
    </span>
    <div>
        <h1 class="text-2xl font-semibold leading-tight">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-sm text-muted">{{ $subtitle }}</p>
        @endif
    </div>
</div>
