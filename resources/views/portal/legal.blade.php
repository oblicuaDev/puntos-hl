<x-portal.layout title="Términos y condiciones">
    <div class="bg-white rounded-xl shadow p-6">
        <h1 class="text-xl font-semibold mb-4 flex items-center gap-2">
            @svg('heroicon-o-scale', 'h-5 w-5 text-azul')
            {{ $page->title ?? 'Términos y condiciones' }}
        </h1>
        <div class="prose prose-sm max-w-none">
            {!! $page->body_html ?? '<p>Próximamente.</p>' !!}
        </div>
    </div>
</x-portal.layout>
