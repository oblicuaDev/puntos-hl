<?php

namespace App\Providers;

use App\Services\BillingSheet\BillingSheet;
use App\Services\BillingSheet\GoogleBillingSheet;
use App\Services\Payments\PaymentGatewayContract;
use App\Services\Payments\PayzenGateway;
use App\Services\Payments\StubPaymentGateway;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use App\Services\Sms\SmsToSender;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsSender::class, function () {
            return match (config('services.sms.driver')) {
                'smsto' => $this->app->make(SmsToSender::class),
                default => $this->app->make(LogSmsSender::class),
            };
        });

        $this->app->bind(BillingSheet::class, GoogleBillingSheet::class);

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
