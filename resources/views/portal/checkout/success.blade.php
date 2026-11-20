<x-portal.layout title="Canje confirmado">
    <div class="bg-white rounded-xl shadow p-6 text-center space-y-4 mt-6">
        <h1 class="text-xl font-semibold">¡Canje confirmado!</h1>
        <p class="text-gray-600 text-sm">Código de tu canje: <strong>{{ $redemption->code }}</strong></p>
        <p class="text-gray-600 text-sm">
            Nos pondremos en contacto contigo en el menor tiempo posible para agendar tu cita, o puedes comunicarte a nuestros canales de atención.
        </p>
        <a href="{{ route('portal.dashboard') }}" class="inline-block w-full rounded-lg bg-gray-900 text-white py-2.5 font-medium">
            Ir a mi cuenta
        </a>
    </div>
</x-portal.layout>
