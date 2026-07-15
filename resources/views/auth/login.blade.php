<x-guest-layout title="Connexion">
    @if (session('status'))
        <p class="auth-status">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('login') }}" class="auth-form">
        @csrf

        <div class="field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
            @error('email') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="password">Mot de passe</label>
            <input id="password" type="password" name="password" required autocomplete="current-password">
            <a class="field-link" href="{{ route('password.request') }}">Mot de passe oublié ?</a>
        </div>

        <label class="checkbox">
            <input type="checkbox" name="remember"> Se souvenir de moi
        </label>

        <button type="submit" class="btn">Se connecter</button>
    </form>

    <p class="auth-alt">Pas encore de compte ? <a href="{{ route('register') }}">S'inscrire</a></p>
</x-guest-layout>
