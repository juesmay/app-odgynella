<?php

namespace App\Providers;

use App\Models\Sede;
use App\Support\CurrentSede;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('es');
        CarbonImmutable::setLocale('es');

        View::composer('layouts.app', function ($view) {
            if (auth()->check()) {
                $view->with('currentSede', CurrentSede::get());
                $view->with('allSedes', Sede::where('active', true)->orderBy('id')->get());
            }
        });
    }
}
