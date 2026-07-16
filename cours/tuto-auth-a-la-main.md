# Tuto — L'authentification à la main (Partie I web)

> But : construire toi-même l'auth web (register / login / logout) **sans Breeze ni Fortify**, pour voir chaque fil. Session + cookie, guard `web`. Tu écris le code ; ce guide te dit **où**, **quoi**, et **pourquoi**.
>
> Rappels utiles avant de commencer : [Cours 4](04-routing-controllers-validation.md) (routes/controllers/validation), [Cours 6](06-auth-permissions.md) (auth vs autorisation), [Cours 9](09-packages-expliques.md) (spatie/permission).

---

## 0. Où on en est (étape A, déjà faite dans le repo)

Ces briques sont déjà en place, tu n'as PAS à les refaire :

- `spatie/laravel-permission` installé, tables migrées, `config/permission.php` publié.
- Trait `HasRoles` sur `app/Models/User.php`.
- `RolesAndPermissionsSeeder` :
    - permissions :
        - create seances
        - update seances
        - cancel seances
        - delete seances
        - manage participants
    - rôles :
        - admin
        - manager
        - coach
        - collaborator

-`DatabaseSeeder` crée **3 users de test** (mot de passe `password`) :
  - `admin@example.com` (admin) · `coach@example.com` (coach) · `collab@example.com` (collaborator)

Rejoue-les quand tu veux avec `make fresh` (= `migrate:fresh --seed`).

### ⚠️ Deux pièges à évacuer tout de suite

1. **Tu ne crées PAS la table `users`** — elle existe déjà (fournie par Laravel, migrée). Tu t'en sers.
2. **Tu ne hashes PAS le mot de passe à la main.** Le model `User` a `'password' => 'hashed'` dans `casts()` : dès que tu affectes `password`, Laravel le hashe tout seul. Pas de `Hash::make(...)` à écrire.

---

## 1. La carte : où vivent les fichiers d'auth

| Rôle | Emplacement |
|---|---|
| Les routes | `routes/web.php` |
| Le controller d'inscription | `app/Http/Controllers/Auth/RegisteredUserController.php` |
| Le controller de connexion/déconnexion | `app/Http/Controllers/Auth/AuthenticatedSessionController.php` |
| Les vues (formulaires) | `resources/views/auth/login.blade.php` et `register.blade.php` |
| Une page protégée pour tester | `resources/views/dashboard.blade.php` |

> 💡 On sépare inscription et connexion en **deux controllers** (comme le fait Breeze) : chacun a une responsabilité claire. C'est l'esprit « controller mince » du Cours 4.

Génère les deux controllers avec artisan (le sous-dossier `Auth/` est créé tout seul) :

```bash
sail artisan make:controller Auth/RegisteredUserController --no-interaction
sail artisan make:controller Auth/AuthenticatedSessionController --no-interaction
```

---

## 2. Étape B1 — les routes

Dans `routes/web.php`, ajoute deux groupes. Le middleware **existe déjà** (tu ne le codes pas) : `guest` = réservé aux visiteurs non connectés, `auth` = réservé aux connectés.

```php
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;

// Réservé aux visiteurs NON connectés
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

// Réservé aux connectés
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');
});
```

> 💡 Le couple **GET + POST** par formulaire : le `GET` affiche la page (`create`), le `POST` traite l'envoi (`store`). Le `->name(...)` te permet d'utiliser `route('login')` partout au lieu d'écrire l'URL en dur (cf. Cours 4).

Vérifie : `sail artisan route:list --path=login` doit lister tes routes.

---

## 3. Étape B2 — l'inscription

### Le controller `RegisteredUserController`

Deux méthodes : `create()` affiche le formulaire, `store()` traite l'inscription.

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        // 1) Valider (cf. Cours 4). 'confirmed' => exige un champ password_confirmation identique.
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        // 2) Créer le user. Le mot de passe est hashé AUTOMATIQUEMENT (cast 'hashed').
        $user = User::create($validated);

        // 3) Rôle par défaut d'une inscription publique.
        $user->assignRole('collaborator');

        // 4) Connecter le nouvel utilisateur dans la foulée, puis rediriger.
        Auth::login($user);

        return redirect()->route('dashboard');
    }
}
```

> ⚠️ `User::create($validated)` n'écrit que les champs autorisés par `#[Fillable(['name','email','password'])]` sur le model (garde-fou mass assignment, Cours 3). Si tu ajoutes un champ, pense à le rendre `fillable`.

### La vue `resources/views/auth/register.blade.php`

Minimal mais fonctionnel : `@csrf` (obligatoire sur tout POST — protection CSRF), `old()` pour re-remplir après erreur, `@error` pour afficher les messages.

```blade
<form method="POST" action="{{ route('register') }}">
    @csrf

    <label>Nom
        <input type="text" name="name" value="{{ old('name') }}" required>
    </label>
    @error('name') <p>{{ $message }}</p> @enderror

    <label>Email
        <input type="email" name="email" value="{{ old('email') }}" required>
    </label>
    @error('email') <p>{{ $message }}</p> @enderror

    <label>Mot de passe
        <input type="password" name="password" required>
    </label>
    @error('password') <p>{{ $message }}</p> @enderror

    <label>Confirmer le mot de passe
        <input type="password" name="password_confirmation" required>
    </label>

    <button type="submit">S'inscrire</button>
</form>
```

> 💡 `password_confirmation` : c'est le nom EXACT que la règle `confirmed` attend (elle compare `password` et `password_confirmation`). Le `@csrf` génère un token caché ; sans lui, Laravel rejette le POST avec un 419.

---

## 4. Étape B3 — la connexion

### Ajoute à `AuthenticatedSessionController`

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Auth::attempt cherche le user, compare le mot de passe hashé, ouvre la session si OK.
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Ces identifiants ne correspondent à aucun compte.',
            ]);
        }

        // Sécurité : régénérer l'ID de session après login (anti session fixation).
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        // Vider la session et renouveler le token CSRF.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
```

> 💡 `Auth::attempt(['email' => ..., 'password' => ...])` fait tout le sale boulot : il retrouve le user par email, hashe le mot de passe fourni, le compare à celui en base, et — si ça matche — ouvre la session. Tu ne compares jamais les mots de passe toi-même.
>
> 💡 `redirect()->intended(...)` renvoie vers la page que l'utilisateur voulait atteindre avant d'être bloqué par `auth` (sinon vers le dashboard). Confort natif.

### La vue `resources/views/auth/login.blade.php`

```blade
<form method="POST" action="{{ route('login') }}">
    @csrf

    <label>Email
        <input type="email" name="email" value="{{ old('email') }}" required>
    </label>
    @error('email') <p>{{ $message }}</p> @enderror

    <label>Mot de passe
        <input type="password" name="password" required>
    </label>

    <label><input type="checkbox" name="remember"> Se souvenir de moi</label>

    <button type="submit">Se connecter</button>
</form>
```

---

## 5. Étape B4 — la page protégée + le logout

Crée `resources/views/dashboard.blade.php` pour vérifier que tu es bien connecté et voir tes rôles :

```blade
<p>Connecté : {{ auth()->user()->email }}</p>
<p>Rôles : {{ auth()->user()->getRoleNames()->implode(', ') }}</p>

<form method="POST" action="{{ route('logout') }}">
    @csrf
    <button type="submit">Se déconnecter</button>
</form>
```

> 💡 Le logout est un **POST**, pas un lien : une déconnexion modifie l'état (elle détruit la session), donc jamais en GET. D'où le petit formulaire avec `@csrf`.

---

## 6. Étape C — protéger par rôle / permission

`auth` protège « il faut être connecté ». Pour « il faut le bon droit », empile le middleware de spatie (rappel Cours 6 : on teste une **permission**, pas un rôle) :

```php
Route::middleware(['auth', 'permission:create seances'])->group(function () {
    // ... routes de création de séance (à venir)
});
```

> 🔴 Convention XEFI : `permission:create seances`, **pas** `role:coach`. Le rôle n'est qu'un paquet de permissions. La règle fine « ses propres séances » viendra plus tard via une **Policy** (Cours 6 / Recette 2).

---

## 7. Tester

```bash
make fresh          # base propre + 3 users de test
```

1. Va sur `http://localhost/register` → crée un compte → tu dois arriver sur `/dashboard` connecté, rôle `collaborator`.
2. Déconnecte-toi, puis `http://localhost/login` avec `coach@example.com` / `password` → dashboard, rôle `coach`.
3. Essaie `/dashboard` **sans être connecté** → tu dois être renvoyé vers `/login` (middleware `auth`).
4. Essaie `/login` **en étant déjà connecté** → renvoyé ailleurs (middleware `guest`).

Avant de committer : `make check` (Pint + Larastan + tests), comme d'habitude.

---

## Pour aller plus loin (pas obligatoire ici)

- **Extraire la validation en Form Request** (`RegisterRequest`, `LoginRequest`) pour des controllers encore plus minces — cf. Cours 4.
- **Vérification d'email**, réinitialisation de mot de passe : mêmes patterns, à ajouter si besoin.
- **Partie II (API)** : là on ne passe plus par la session mais par un **JWT** (`tymon/jwt-auth`, guard `api`) — cf. [Cours 8](08-api-rest-jwt-lomkit.md).

## Checklist

- [ ] 2 controllers générés dans `app/Http/Controllers/Auth/`
- [ ] Routes register/login/logout + `/dashboard` (groupes `guest` / `auth`)
- [ ] Inscription : valide, crée le user (mot de passe hashé auto), assigne `collaborator`, connecte
- [ ] Connexion : `Auth::attempt` + `session()->regenerate()`
- [ ] Déconnexion : `Auth::logout` + `invalidate` + `regenerateToken` (en POST)
- [ ] Vues avec `@csrf`, `old()`, `@error`
- [ ] Test des 4 scénarios ci-dessus OK · `make check` vert

⬅️ [Sommaire des cours](README.md) · Feuille de route : [XEFI 03 — Recettes](xefi-03-recettes-projet.md)
