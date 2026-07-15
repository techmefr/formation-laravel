<x-guest-layout title="Nouveau mot de passe">
    <p class="auth-hint">Choisis un nouveau mot de passe pour ton compte.</p>

    <form method="POST" action="{{ route('password.update') }}" class="auth-form">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autocomplete="email">
            @error('email') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="password">Nouveau mot de passe</label>
            <input id="password" type="password" name="password" required autofocus autocomplete="new-password">
            @error('password') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="password_confirmation">Confirmer le mot de passe</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
        </div>

        <button type="submit" class="btn">Réinitialiser</button>
    </form>
</x-guest-layout>
