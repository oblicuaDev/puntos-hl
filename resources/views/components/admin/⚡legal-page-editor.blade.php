<?php

use App\Models\LegalPage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    public const SLUG = 'terminos-y-condiciones';

    #[Validate('required|string|max:255')]
    public string $title = '';

    #[Validate('required|string')]
    public string $body_html = '';

    public bool $saved = false;

    public function mount(): void
    {
        $page = LegalPage::where('slug', self::SLUG)->first();

        $this->title = $page->title ?? 'Términos y condiciones — Plan de Fidelización';
        $this->body_html = $page->body_html ?? '';
    }

    public function save(): void
    {
        $data = $this->validate();
        $data['body_html'] = clean($data['body_html']);
        $data['updated_by_admin_user_id'] = Auth::guard('admin')->id();

        LegalPage::updateOrCreate(['slug' => self::SLUG], $data);

        $this->saved = true;
    }
};
?>

<form wire:submit="save" class="bg-white rounded-xl shadow p-6 space-y-4 max-w-3xl">
    <h2 class="font-semibold">Términos y condiciones</h2>

    @if ($saved)
        <p class="text-sm text-emerald-600">Guardado correctamente.</p>
    @endif

    <div>
        <label class="block text-sm font-medium">Título</label>
        <input type="text" wire:model="title" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
        @error('title') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div wire:ignore x-data="{ content: @entangle('body_html') }">
        <label class="block text-sm font-medium">Contenido</label>
        <input id="trix-legal-page" type="hidden" x-model="content">
        <trix-editor input="trix-legal-page" x-on:trix-change="content = $event.target.value" class="mt-1 block"></trix-editor>
        @error('body_html') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <button type="submit" class="rounded-full bg-azul text-white px-5 py-2 text-sm font-medium border-2 border-transparent transition-all duration-500 hover:bg-white hover:text-azul hover:border-azul">Guardar</button>
</form>
