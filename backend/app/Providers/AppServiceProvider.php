<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Domain\Ports\TurnoRepositoryPort::class,
            \App\Infrastructure\Persistence\Eloquent\Repositories\EloquentTurnoRepository::class
        );
        $this->app->bind(
            \App\Domain\Ports\MovimientoRepositoryPort::class,
            \App\Infrastructure\Persistence\Eloquent\Repositories\EloquentMovimientoRepository::class
        );
        $this->app->bind(
            \App\Domain\Ports\RecetaRepositoryPort::class,
            \App\Infrastructure\Persistence\Eloquent\Repositories\EloquentRecetaRepository::class
        );
        $this->app->bind(
            \App\Domain\Ports\TraspasoRepositoryPort::class,
            \App\Infrastructure\Persistence\Eloquent\Repositories\EloquentTraspasoRepository::class
        );
        $this->app->bind(
            \App\Domain\Ports\AuditoriaRepositoryPort::class,
            \App\Infrastructure\Persistence\Eloquent\Repositories\EloquentAuditoriaRepository::class
        );
        $this->app->bind(
            \App\Domain\Ports\UsuarioRepositoryPort::class,
            \App\Infrastructure\Persistence\Eloquent\Repositories\EloquentUsuarioRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
