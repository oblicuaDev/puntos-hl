@props(['title' => '', 'onClose' => 'closeModal', 'maxWidth' => 'max-w-xl'])

<div class="fixed inset-0 z-30 flex items-start justify-center overflow-y-auto bg-black/40 p-4 pt-10 sm:pt-16" wire:click.self="{{ $onClose }}" wire:transition.opacity>
    <div class="w-full {{ $maxWidth }} rounded-brand bg-white shadow-xl ring-1 ring-black/5">
        <div class="flex items-center justify-between border-b border-black/5 px-6 py-4">
            <h2 class="text-base font-semibold text-ink">{{ $title }}</h2>
            <button type="button" wire:click="{{ $onClose }}" class="rounded-lg p-1.5 text-muted hover:bg-black/5 hover:text-ink transition-colors" aria-label="Cerrar">
                @svg('heroicon-o-x-mark', 'h-5 w-5')
            </button>
        </div>
        <div class="p-6 max-h-[80vh] overflow-y-auto">
            {{ $slot }}
        </div>
    </div>
</div>
