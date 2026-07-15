<x-guest-layout title="Mot de passe oublié">
    <p class="auth-hint">
        Indique ton email : on t'envoie un lien pour choisir un nouveau mot de passe.
    </p>

    @if (session('status'))
        <p class="auth-status">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="auth-form">
        @csrf

        <div class="field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
            @error('email') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="btn">Envoyer le lien</button>
    </form>

    <p class="auth-alt"><a href="{{ route('login') }}">Retour à la connexion</a></p>
</x-guest-layout>
