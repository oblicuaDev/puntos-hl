@props(['target' => null, 'loadingText' => 'Procesando…'])

<button
    {{ $attributes->class(['inline-flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed'])->merge(['type' => 'submit']) }}
    wire:loading.attr="disabled"
    @if ($target) wire:target="{{ $target }}" @endif
>
    <svg @if ($target) wire:target="{{ $target }}" @endif wire:loading class="animate-spin h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
    </svg>
    <span @if ($target) wire:target="{{ $target }}" @endif wire:loading.remove>{{ $slot }}</span>
    <span @if ($target) wire:target="{{ $target }}" @endif wire:loading>{{ $loadingText }}</span>
</button>
