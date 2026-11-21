@props(['icon' => 'o-pencil-square'])

<h2 class="font-semibold flex items-center gap-2">
    {{ svg('heroicon-'.$icon, 'h-4 w-4 text-azul') }}
    {{ $slot }}
</h2>
