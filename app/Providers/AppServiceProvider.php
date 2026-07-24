<?php

namespace App\Providers;

use Functional\Seances\Events\SeanceCancelled;
use Functional\Seances\Events\SeanceCreated;
use Functional\Seances\Events\SeanceDeleted;
use Functional\Seances\Listeners\NotifyCoachOfNewSeance;
use Functional\Seances\Listeners\NotifyCoachOfSeanceDeletion;
use Functional\Seances\Listeners\NotifyParticipantsOfCancellation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(function () {
            return Password::min(12)
                ->mixedCase()
                ->numbers()
                ->symbols();
        });

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        Event::listen(SeanceCreated::class, NotifyCoachOfNewSeance::class);
        Event::listen(SeanceCancelled::class, NotifyParticipantsOfCancellation::class);
        Event::listen(SeanceDeleted::class, NotifyCoachOfSeanceDeletion::class);
    }
}
