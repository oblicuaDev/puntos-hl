<x-portal.layout>
    <div class="space-y-6">
        @foreach ($banners as $banner)
            <a href="{{ $banner->link_url ?? '#' }}" class="block rounded-brand overflow-hidden shadow-sm">
                <img src="{{ Storage::url($banner->image_path) }}" alt="" class="w-full object-cover">
            </a>
        @endforeach

        @auth('patient')
            <section class="bg-white rounded-brand shadow-sm ring-1 ring-black/5 p-6 space-y-4">
                <p class="text-sm text-muted">Hola, {{ auth('patient')->user()->first_name }}</p>
                <h1 class="text-2xl font-semibold text-marino leading-tight">Tus puntos están listos para canjear</h1>
                <a href="{{ route('portal.rewards.index') }}" wire:navigate class="flex items-center justify-center gap-2 w-full rounded-brand bg-azul text-white py-3 font-semibold transition-colors hover:bg-azul-dark">
                    @svg('heroicon-o-gift', 'h-4 w-4')
                    Ver catálogo de premios
                </a>
            </section>
        @else
            <section class="-mx-5 -mt-5 relative h-48 overflow-hidden">
                <img src="{{ asset('img/marketing/hero-bienestar.webp') }}" alt="Pacientes de Harker Lloreda" class="w-full h-full object-cover object-right">
                <div class="absolute inset-0 bg-gradient-to-t from-marino/70 via-marino/10 to-transparent"></div>
            </section>

            <section class="space-y-5">
                <h1 class="text-3xl font-semibold text-marino leading-tight">Gana puntos en cada procedimiento, en el lugar donde ya confías</h1>
                <p class="text-muted text-sm leading-relaxed">Hace parte de la experiencia Harker Lloreda: 15 años y más de 8.000 pacientes acompañados. Ahora, cada procedimiento de piel, cuerpo, cara y cabello también suma puntos para tus próximos tratamientos.</p>
            </section>

            <section class="bg-white rounded-brand shadow-sm ring-1 ring-black/5 divide-y divide-black/5 px-5">
                <div class="flex gap-4 py-4">
                    <span class="h-8 w-8 rounded-full bg-azul text-white text-sm font-semibold flex items-center justify-center shrink-0">1</span>
                    <div>
                        <p class="text-sm font-semibold text-marino">Crea tu cuenta</p>
                        <p class="text-xs text-muted mt-0.5">Con tu documento y correo, en menos de un minuto.</p>
                    </div>
                </div>
                <div class="flex gap-4 py-4">
                    <span class="h-8 w-8 rounded-full bg-azul text-white text-sm font-semibold flex items-center justify-center shrink-0">2</span>
                    <div>
                        <p class="text-sm font-semibold text-marino">Gana puntos</p>
                        <p class="text-xs text-muted mt-0.5">Cada procedimiento y compra en Harker Lloreda se registra automáticamente.</p>
                    </div>
                </div>
                <div class="flex gap-4 py-4">
                    <span class="h-8 w-8 rounded-full bg-azul text-white text-sm font-semibold flex items-center justify-center shrink-0">3</span>
                    <div>
                        <p class="text-sm font-semibold text-marino">Canjea por premios</p>
                        <p class="text-xs text-muted mt-0.5">Solo puntos o puntos más dinero, tú decides.</p>
                    </div>
                </div>
            </section>

            <div class="space-y-3">
                <a href="{{ route('portal.register') }}" wire:navigate class="flex items-center justify-center gap-2 w-full rounded-brand bg-azul text-white py-3 font-semibold transition-colors hover:bg-azul-dark">
                    @svg('heroicon-o-user-plus', 'h-4 w-4')
                    Crear mi cuenta
                </a>
                <a href="{{ route('portal.login') }}" wire:navigate class="flex items-center justify-center gap-2 w-full rounded-brand border-2 border-azul text-azul py-2.5 font-semibold transition-colors hover:bg-azul hover:text-white">
                    @svg('heroicon-o-lock-closed', 'h-4 w-4')
                    Ya tengo cuenta
                </a>
            </div>
        @endauth

        <div class="text-center">
            <a href="{{ route('portal.legal') }}" wire:navigate class="inline-flex items-center gap-1 text-xs text-muted underline underline-offset-2">
                @svg('heroicon-o-scale', 'h-3.5 w-3.5')
                Términos y condiciones
            </a>
        </div>
    </div>
</x-portal.layout>
