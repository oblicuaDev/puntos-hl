<x-portal.layout>
    <div class="space-y-4">
        @foreach ($banners as $banner)
            <a href="{{ $banner->link_url ?? '#' }}" class="block rounded-xl overflow-hidden">
                <img src="{{ Storage::url($banner->image_path) }}" class="w-full object-cover">
            </a>
        @endforeach

        <div class="bg-white rounded-xl shadow p-6 text-center space-y-4">
            <h1 class="text-xl font-semibold">Plan de Fidelización Harker Lloreda</h1>
            <p class="text-gray-600 text-sm">Acumula puntos con tus compras y procedimientos, y redímelos por premios.</p>

            @auth('patient')
                <a href="{{ route('portal.rewards.index') }}" class="inline-block w-full rounded-lg bg-gray-900 text-white py-2.5 font-medium">
                    Ver catálogo de premios
                </a>
            @else
                <a href="{{ route('portal.login') }}" class="inline-block w-full rounded-lg bg-gray-900 text-white py-2.5 font-medium">
                    Ingresar
                </a>
                <a href="{{ route('portal.register') }}" class="inline-block w-full rounded-lg border border-gray-900 py-2.5 font-medium">
                    Crear cuenta
                </a>
            @endauth

            <a href="{{ route('portal.legal') }}" class="block text-xs text-gray-400 underline">Términos y condiciones</a>
        </div>
    </div>
</x-portal.layout>
