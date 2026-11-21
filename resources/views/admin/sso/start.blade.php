<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="theme-color" content="#335C82">
    <title>Plan de Fidelización — Acceso superadmin</title>
    @vite(['resources/css/app.css'])
    {{ Vite::fonts() }}
</head>
<body class="min-h-screen bg-[#FAF8F5] font-sans text-ink flex items-center justify-center p-6">
    <div class="max-w-sm w-full p-8 bg-white rounded-brand shadow text-center space-y-5">
        <x-brand-logo class="h-6 w-auto text-azul mx-auto" />

        <span class="inline-flex items-center justify-center h-12 w-12 rounded-full bg-azul/10 text-azul">
            @svg('heroicon-o-lock-closed', 'h-5 w-5')
        </span>

        <p class="text-muted text-sm">Inicia sesión desde el panel de WordPress de Harker Lloreda para entrar al superadmin.</p>

        @error('sso')
            <p class="text-sm text-red-600 flex items-center justify-center gap-1.5">
                @svg('heroicon-o-exclamation-triangle', 'h-4 w-4 shrink-0')
                {{ $message }}
            </p>
        @enderror

        <a href="{{ $wpAdminUrl }}" id="sso-start-link"
           class="inline-flex items-center justify-center gap-2 w-full rounded-brand bg-azul text-white py-2.5 font-medium text-center border-2 border-transparent transition-all duration-500 hover:bg-white hover:text-azul hover:border-azul">
            <svg id="sso-start-spinner" class="animate-spin h-4 w-4 shrink-0" style="display:none" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span id="sso-start-icon">@svg('heroicon-o-arrow-right-on-rectangle', 'h-4 w-4')</span>
            <span id="sso-start-label">Entrar con WordPress</span>
        </a>
    </div>
    <script src="{{ asset('js/sso-start.js') }}"></script>
</body>
</html>
