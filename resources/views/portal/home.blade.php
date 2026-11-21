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
            <section class="space-y-5 pt-2">
                <span class="inline-flex items-center rounded-full bg-dermo text-marino text-[11px] font-semibold uppercase tracking-wider px-3 py-1">Plan de fidelización</span>
                <h1 class="text-3xl font-semibold text-marino leading-tight">Tus procedimientos ahora suman puntos</h1>
                <p class="text-muted text-sm leading-relaxed">Acumula puntos con tus compras y procedimientos, y redímelos por premios.</p>
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
                        <p class="text-xs text-muted mt-0.5">Cada procedimiento y compra se registra automáticamente.</p>
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
