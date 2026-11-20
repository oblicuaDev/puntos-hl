<?php

namespace App\Providers;

use App\Services\Payments\PaymentGatewayContract;
use App\Services\Payments\PayzenGateway;
use App\Services\Payments\StubPaymentGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGatewayContract::class, function () {
            return match (config('payments.driver')) {
                'payzen' => $this->app->make(PayzenGateway::class),
                default => $this->app->make(StubPaymentGateway::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
