<?php

namespace App\Providers;

use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
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
    public function boot(Vite $vite): void
    {
        $vite->createAssetPathsUsing(function (string $path, ?bool $secure = null): string {
            $request = $this->app->make(Request::class);
            $baseUrl = rtrim($request->getBaseUrl(), '/');

            return $baseUrl.'/'.ltrim($path, '/');
        });
    }
}
