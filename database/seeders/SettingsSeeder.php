<?php

namespace Database\Seeders;

use App\Services\SettingsService;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = app(SettingsService::class);

        $settings->set('accrual_cop_per_point', 1000, 'decimal');
        $settings->set('redemption_points_per_100_cop', 3, 'decimal');
        $settings->set('min_points_percentage', 10, 'decimal');
        $settings->set('points_expiration_months', 12, 'integer');
        $settings->set('management_notification_email', 'gerencia@harkerlloreda.com', 'string');
        $settings->set('contact_phones', ['+57 300 000 0000'], 'json');
    }
}
