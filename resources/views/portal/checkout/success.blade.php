<x-portal.layout title="Canje confirmado">
    <div class="bg-white rounded-xl shadow p-6 text-center space-y-4 mt-6">
        <span class="inline-flex items-center justify-center h-14 w-14 rounded-full bg-emerald-50 text-emerald-600 mx-auto">
            @svg('heroicon-o-check-circle', 'h-8 w-8')
        </span>
        <h1 class="text-xl font-semibold">Canje confirmado</h1>
        <p class="text-muted text-sm">Código de tu canje: <strong>{{ $redemption->code }}</strong></p>
        <p class="text-muted text-sm">
            Nos pondremos en contacto contigo en el menor tiempo posible para agendar tu cita, o puedes comunicarte a nuestros canales de atención.
        </p>
        <a href="{{ route('portal.dashboard') }}" wire:navigate class="inline-flex items-center justify-center gap-2 w-full rounded-brand bg-azul text-white py-2.5 font-medium text-center border-2 border-transparent transition-all duration-500 hover:bg-white hover:text-azul hover:border-azul">
            @svg('heroicon-o-user-circle', 'h-4 w-4')
            Ir a mi cuenta
        </a>
    </div>
</x-portal.layout>
