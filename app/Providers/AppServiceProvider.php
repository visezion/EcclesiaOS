<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Services\PaymentGatewayManager;
use App\Support\OrganizationTerminology;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, PaymentGatewayManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Vite $vite): void
    {
        View::composer('*', function ($view): void {
            $terminology = OrganizationTerminology::forRequest(request());
            $view->with('terminology', $terminology);
            $view->with('term', function (?string $text) use ($terminology): string {
                $source = (string) $text;
                $localized = __($source);

                return OrganizationTerminology::translate(is_string($localized) ? $localized : $source, $terminology);
            });
        });

        if (parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https') {
            URL::forceScheme('https');
        }

        $vite->createAssetPathsUsing(function (string $path, ?bool $secure = null): string {
            $request = $this->app->make(Request::class);
            $baseUrl = rtrim($request->getBaseUrl(), '/');

            return $baseUrl.'/'.ltrim($path, '/');
        });
    }
}
