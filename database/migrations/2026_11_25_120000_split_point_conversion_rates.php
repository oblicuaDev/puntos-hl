<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reemplaza la tasa única `cop_per_point` por dos tasas independientes
 * (definición confirmada por H&L, 2026-11-25):
 *   - accrual_cop_per_point = 1000  → 1 punto por cada $1.000 COP pagados.
 *   - redemption_points_per_100_cop = 3 → 3 puntos por cada $100 COP del procedimiento.
 *
 * Las ventas ya registradas conservan su points_awarded/exchange_rate_applied
 * y los canjes su points_used/money_amount_cop: nada histórico se recalcula.
 * Los canjes nuevos guardan además la tasa de redención aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            'accrual_cop_per_point' => '1000',
            'redemption_points_per_100_cop' => '3',
        ] as $key => $value) {
            if (DB::table('settings')->where('key', $key)->doesntExist()) {
                DB::table('settings')->insert([
                    'key' => $key, 'value' => $value, 'type' => 'decimal',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        DB::table('settings')->where('key', 'cop_per_point')->delete();

        Schema::table('redemptions', function (Blueprint $table) {
            $table->decimal('redemption_rate_applied', 12, 4)->nullable()->after('points_percentage_applied');
        });

        Cache::forget('hlfid:settings');
    }

    public function down(): void
    {
        Schema::table('redemptions', function (Blueprint $table) {
            $table->dropColumn('redemption_rate_applied');
        });

        $now = now();
        DB::table('settings')->whereIn('key', ['accrual_cop_per_point', 'redemption_points_per_100_cop'])->delete();
        DB::table('settings')->insert([
            'key' => 'cop_per_point', 'value' => '100', 'type' => 'decimal',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        Cache::forget('hlfid:settings');
    }
};
