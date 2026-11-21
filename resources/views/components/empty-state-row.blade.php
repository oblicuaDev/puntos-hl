@props(['colspan' => 1, 'icon' => 'o-inbox', 'message' => 'Nada por aquí todavía.'])

<tr>
    <td colspan="{{ $colspan }}" class="p-10 text-center text-muted">
        <div class="flex flex-col items-center gap-2">
            {{ svg('heroicon-'.$icon, 'h-8 w-8 text-steel') }}
            <span class="text-sm">{{ $message }}</span>
        </div>
    </td>
</tr>
