<x-guest-layout title="Erreur {{ $code }}">

    <div class="error-page">

        <h1 class="error-code">
{{ $code }}
</h1>

<p class="error-message">
    {{ $message }}
</p>

<button class="btn"
        onclick="if(history.length > 1) history.back(); else window.location='/'">
    Retour
</button>

</div>

</x-guest-layout>
