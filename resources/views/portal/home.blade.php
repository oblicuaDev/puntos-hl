<x-portal.layout>
    <div class="space-y-4">
        @foreach ($banners as $banner)
            <a href="{{ $banner->link_url ?? '#' }}" class="block rounded-xl overflow-hidden">
                <img src="{{ Storage::url($banner->image_path) }}" class="w-full object-cover">
            </a>
        @endforeach

        <div class="bg-white rounded-xl shadow p-6 text-center space-y-4">
            <h1 class="text-xl font-semibold">Plan de Fidelización Harker Lloreda</h1>
            <p class="text-muted text-sm">Acumula puntos con tus compras y procedimientos, y redímelos por premios.</p>

            @auth('patient')
                <a href="{{ route('portal.rewards.index') }}" class="inline-block w-full rounded-brand bg-azul text-white py-2.5 font-medium text-center border-2 border-transparent transition-all duration-500 hover:bg-white hover:text-azul hover:border-azul">
                    Ver catálogo de premios
                </a>
            @else
                <a href="{{ route('portal.login') }}" class="inline-block w-full rounded-brand bg-azul text-white py-2.5 font-medium text-center border-2 border-transparent transition-all duration-500 hover:bg-white hover:text-azul hover:border-azul">
                    Ingresar
                </a>
                <a href="{{ route('portal.register') }}" class="inline-block w-full rounded-brand border-2 border-azul text-azul py-2.5 font-medium text-center transition-all duration-500 hover:bg-azul hover:text-white">
                    Crear cuenta
                </a>
            @endauth

            <a href="{{ route('portal.legal') }}" class="block text-xs text-muted/70 underline">Términos y condiciones</a>
        </div>
    </div>
</x-portal.layout>
