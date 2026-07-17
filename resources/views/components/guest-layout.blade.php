@props(['title' => '', 'subtitle' => 'Séances de sport — XEFI Santé Sport'])
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — Séances de sport</title>
    <style>
        :root {
            --red: #c8102e;
            --red-hover: #a50d26;
            --red-accent: #e11d2a;
            --red-link: #ff6b6f;
            --field-bg: #0e1120;
            --border: #2c3252;
            --ring: rgba(225, 29, 42, .40);
            --text: #f4f5f8;
            --muted: #aab2c5;
            --error: #ff9b9b;
        }
        * { box-sizing: border-box; }
        html, body { min-height: 100%; }
        body {
            margin: 0;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            color: var(--text);
            background: linear-gradient(135deg, oklch(20.5% 0.0511 277.6) 12.5%, oklch(41.6% 0.1444 288.5) 37.5%, oklch(70.4% 0.1441 303.8) 62.5%, oklch(87.7% 0.0652 342.7) 87.5%);
            background: linear-gradient(135deg in oklab, oklch(20.5% 0.0511 277.6) 12.5%, oklch(41.6% 0.1444 288.5) 37.5%, oklch(70.4% 0.1441 303.8) 62.5%, oklch(87.7% 0.0652 342.7) 87.5%) fixed;
            background-size: 300% 300%;
            animation: yozaki-flow 36s ease-in-out infinite;
        }
        @keyframes yozaki-flow {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        @media (prefers-reduced-motion: reduce) {
            body { animation: none; }
        }
        .auth-card {
            width: min(92vw, 25rem);
            background: linear-gradient(180deg, #1b2038, #12162a);
            border: 1px solid rgba(255, 255, 255, .07);
            border-top: 3px solid var(--red);
            border-top-right-radius: 1.5rem;
            border-bottom-left-radius: 1.5rem;
            box-shadow: 0 24px 55px rgba(0, 0, 0, .6);
            overflow: hidden;
        }
        .auth-card__header {
            padding: 1.4rem 1.6rem 1.2rem;
            color: #fff;
            border-bottom: 1px solid rgba(255, 255, 255, .08);
        }
        .auth-card__title {
            margin: 0;
            padding-left: .65rem;
            border-left: 4px solid var(--red);
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: .02em;
        }
        .auth-card__subtitle {
            margin: .2rem 0 0;
            font-size: .8rem;
            opacity: .9;
        }
        .auth-card__body { padding: 1.5rem 1.6rem 1.75rem; }
        .auth-form { display: flex; flex-direction: column; gap: 1rem; }
        .field { display: flex; flex-direction: column; gap: .35rem; }
        .field label {
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: #e6e8ef;
        }
        .field input {
            width: 100%;
            height: 2.7rem;
            padding: 0 .85rem;
            font-size: .95rem;
            color: var(--text);
            background: var(--field-bg);
            border: 2px solid var(--border);
            border-radius: .6rem;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        .field input::placeholder { color: var(--muted); }
        .field input:focus-visible {
            border-color: #fff;
            box-shadow: 0 0 0 4px var(--ring);
        }
        .field__error {
            margin: 0;
            font-size: .8rem;
            font-weight: 600;
            color: var(--error);
        }
        .checkbox {
            display: flex;
            align-items: center;
            gap: .5rem;
            font-size: .85rem;
            color: var(--muted);
        }
        .checkbox input { width: 1rem; height: 1rem; accent-color: var(--red-accent); }
        .btn {
            height: 2.8rem;
            border: none;
            border-radius: .6rem;
            cursor: pointer;
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            background: var(--red);
            box-shadow: 0 6px 16px -6px rgba(200, 16, 46, .8);
            transition: background .15s, transform .05s;
        }
        .btn:hover { background: var(--red-hover); }
        .btn:active { transform: translateY(1px); }
        .btn:focus-visible { outline: 3px solid var(--ring); outline-offset: 2px; }
        .auth-hint {
            margin: 0 0 1.1rem;
            font-size: .88rem;
            line-height: 1.4;
            color: var(--muted);
        }
        .auth-status {
            margin: 0 0 1.1rem;
            padding: .65rem .85rem;
            font-size: .85rem;
            font-weight: 600;
            color: #86efac;
            background: rgba(34, 197, 94, .14);
            border: 1px solid rgba(34, 197, 94, .35);
            border-radius: .5rem;
        }
        .field-link {
            align-self: flex-end;
            font-size: .8rem;
            font-weight: 700;
            color: var(--red-link);
            text-decoration: none;
        }
        .field-link:hover { text-decoration: underline; }
        .auth-alt {
            margin: 1.1rem 0 0;
            text-align: center;
            font-size: .85rem;
            color: var(--muted);
        }
        .auth-alt a { color: var(--red-link); font-weight: 700; text-decoration: none; }
        .auth-alt a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <main class="auth-card">
        <header class="auth-card__header">
            <p class="auth-card__title">{{ $title }}</p>
            <p class="auth-card__subtitle">{{ $subtitle }}</p>
        </header>
        <div class="auth-card__body">
            {{ $slot }}
        </div>
    </main>
</body>
</html>
