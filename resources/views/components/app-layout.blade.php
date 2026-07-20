@props(['title' => ''])
<!DOCTYPE html>
<html lang="fr" data-theme="sport">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — Séances de sport</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bricolage-grotesque:600,700|instrument-sans:400,500,600" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-dvh text-base-content">
    <div class="gradient-yozaki" aria-hidden="true"></div>

    <header class="border-b border-white/10 bg-base-200/95 backdrop-blur">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-6 gap-y-3 px-4 py-4 sm:px-6">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                <a href="{{ route('seances.index') }}" class="flex flex-col leading-tight">
                    <span class="border-l-4 border-primary pl-2 text-xl font-extrabold tracking-wide">Séances de sport</span>
                    <span class="pl-2 text-xs text-base-content/70">XEFI Santé Sport</span>
                </a>
                <nav aria-label="Navigation principale" class="flex gap-1">
                    <a href="{{ route('seances.index') }}" @class(['btn btn-ghost btn-sm', 'btn-active' => request()->routeIs('seances.index')]) @if (request()->routeIs('seances.index')) aria-current="page" @endif>Séances</a>
                </nav>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm text-base-content/80">{{ auth()->user()?->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm">Se déconnecter</button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
        @if (session('notification'))
            <div role="alert" aria-live="polite" class="alert {{ session('notification')['type'] === 'success' ? 'alert-success' : 'alert-error' }} mb-6">
                <span>{{ session('notification')['message'] }}</span>
            </div>
        @endif

        {{ $slot }}
    </main>

    @stack('scripts')
</body>
</html>
