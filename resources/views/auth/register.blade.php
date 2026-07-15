<x-guest-layout title="Inscription">
    <form method="POST" action="{{ route('register') }}" class="auth-form">
        @csrf

        <div class="field">
            <label for="name">Nom</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
            @error('name') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
            @error('email') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="password">Mot de passe</label>
            <input id="password" type="password" name="password" required autocomplete="new-password">
            @error('password') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="password_confirmation">Confirmer le mot de passe</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
        </div>

        <button type="submit" class="btn">S'inscrire</button>
    </form>

    <p class="auth-alt">Déjà un compte ? <a href="{{ route('login') }}">Se connecter</a></p>
</x-guest-layout>
