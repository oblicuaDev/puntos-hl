@props(['active' => true, 'trueLabel' => 'Activo', 'falseLabel' => 'Inactivo'])

@if ($active)
    <span {{ $attributes->class(['inline-flex items-center gap-1 rounded-full bg-emerald-50 text-emerald-700 px-2.5 py-1 text-xs font-medium']) }}>
        @svg('heroicon-s-check-circle', 'h-3.5 w-3.5')
        {{ $trueLabel }}
    </span>
@else
    <span {{ $attributes->class(['inline-flex items-center gap-1 rounded-full bg-black/5 text-muted px-2.5 py-1 text-xs font-medium']) }}>
        @svg('heroicon-s-x-circle', 'h-3.5 w-3.5')
        {{ $falseLabel }}
    </span>
@endif
