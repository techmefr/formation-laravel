<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Tableau de bord — Séances de sport</title>
</head>
<body>
    <p>Connecté : {{ auth()->user()->email }} ({{ auth()->user()->getRoleNames()->implode(', ') ?: 'aucun rôle' }})</p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Se déconnecter</button>
    </form>
</body>
</html>
