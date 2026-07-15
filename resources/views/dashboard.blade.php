<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — Séances de sport</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2.5rem;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            color: #f4f5f8;
            background: #0f1424 url('/images/sport-bg.jpg') center center / cover no-repeat;
        }
        h1 {
            margin: 0;
            font-size: clamp(2.5rem, 12vw, 7rem);
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
            text-shadow: 0 6px 30px rgba(0, 0, 0, .7);
        }
        button {
            padding: .65rem 1.5rem;
            border: 2px solid #e11d2a;
            border-radius: .6rem;
            background: transparent;
            color: #fff;
            font-size: .95rem;
            font-weight: 700;
            cursor: pointer;
            transition: background .15s;
        }
        button:hover { background: #c8102e; }
    </style>
</head>
<body>
    <h1>Dashboard</h1>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Se déconnecter</button>
    </form>
</body>
</html>
