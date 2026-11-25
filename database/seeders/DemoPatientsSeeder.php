<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\Reward;
use App\Models\Sale;
use App\Services\Checkout\InvalidCheckoutException;
use App\Services\Checkout\RedemptionService;
use App\Services\Points\PointAccrualService;
use App\Services\Points\PointRates;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

/**
 * Datos ficticios (~30 pacientes, ventas y redenciones) para que el
 * dashboard administrativo tenga información realista con la que mostrar
 * indicadores, saldos e historiales durante las sesiones de socialización.
 *
 * Reutiliza los servicios reales de la app (PointAccrualService,
 * RedemptionService) para que los lotes de puntos, movimientos y consumos
 * queden exactamente igual que si se hubieran registrado desde el admin.
 * Las fechas se "retroceden" con Carbon::setTestNow() para distribuir las
 * ventas y redenciones en los últimos meses, en vez de amontonarlas hoy.
 *
 * Idempotente: si ya hay pacientes de demo (correo @hlfidelizacion.test),
 * no vuelve a crearlos.
 */
class DemoPatientsSeeder extends Seeder
{
    private const EMAIL_DOMAIN = 'hlfidelizacion.test';

    private const DEMO_PASSWORD = 'Demo12345!';

    private array $firstNames = [
        'Juan', 'Carlos', 'Andrés', 'Santiago', 'Felipe', 'Sebastián', 'Nicolás', 'Daniel',
        'Alejandro', 'David', 'Diego', 'Miguel', 'Jorge', 'Mauricio', 'María', 'Camila',
        'Valentina', 'Isabella', 'Sofía', 'Daniela', 'Mariana', 'Laura', 'Carolina', 'Natalia',
        'Paula', 'Juliana', 'Catalina', 'Alejandra', 'Luisa', 'Andrea',
    ];

    private array $lastNames = [
        'Gómez', 'Rodríguez', 'Martínez', 'López', 'García', 'Pérez', 'Sánchez', 'Ramírez',
        'Torres', 'Flórez', 'Vargas', 'Castro', 'Ortiz', 'Rojas', 'Moreno', 'Muñoz',
        'Jiménez', 'Álvarez', 'Romero', 'Suárez', 'Herrera', 'Medina', 'Castillo', 'Mejía',
        'Rincón', 'Guerrero', 'Cardona', 'Restrepo', 'Zapata', 'Betancur',
    ];

    private array $cities = [
        'Bogotá', 'Medellín', 'Cali', 'Barranquilla', 'Cartagena', 'Bucaramanga',
        'Pereira', 'Santa Marta', 'Manizales', 'Ibagué',
    ];

    public function run(): void
    {
        if (Patient::where('email', 'like', '%@'.self::EMAIL_DOMAIN)->exists()) {
            $this->command?->warn('Ya existen pacientes de demo, no se vuelve a seedear.');

            return;
        }

        $admins = AdminUser::all();
        $procedures = Procedure::where('is_active', true)->get();
        $rewards = Reward::where('is_active', true)->get();

        if ($admins->isEmpty()) {
            $this->command?->warn('No hay admin_users: no se pueden registrar ventas de prueba.');

            return;
        }

        $patients = $this->createPatients(30);

        foreach ($patients as $index => $patient) {
            $this->createSalesFor($patient, $admins, $procedures, $index);
        }

        if ($rewards->isNotEmpty()) {
            foreach ($patients as $index => $patient) {
                // ~70% de los pacientes canjean algo; el resto solo acumula.
                if ($index % 10 < 3) {
                    continue;
                }

                $this->createRedemptionsFor($patient, $rewards);
            }
        }

        // Pasa los lotes vencidos (ventas antiguas) a status "expired" con su
        // movimiento correspondiente, igual que el comando programado real.
        Artisan::call('points:expire-batches');

        $this->command?->info('Pacientes de demo creados: '.count($patients));
    }

    /** @return array<int, Patient> */
    private function createPatients(int $count): array
    {
        $patients = [];
        $usedNames = [];

        for ($i = 1; $i <= $count; $i++) {
            do {
                $first = $this->firstNames[array_rand($this->firstNames)];
                $last1 = $this->lastNames[array_rand($this->lastNames)];
                $last2 = $this->lastNames[array_rand($this->lastNames)];
                $key = $first.$last1.$last2;
            } while (isset($usedNames[$key]));

            $usedNames[$key] = true;

            $documentNumber = (string) (900000000 + $i);
            $slug = str("$first $last1")->slug('.');
            $email = "{$slug}.{$i}@".self::EMAIL_DOMAIN;
            $city = $this->cities[array_rand($this->cities)];
            $phone = '3'.str_pad((string) random_int(0, 99999999), 9, '0', STR_PAD_LEFT);

            $patient = Patient::create([
                'document_type' => 'CC',
                'document_number' => $documentNumber,
                'first_name' => $first,
                'last_name' => "{$last1} {$last2}",
                'email' => $email,
                'phone' => $phone,
                'address' => 'Calle '.random_int(1, 150).' # '.random_int(1, 99).'-'.random_int(1, 99),
                'city' => $city,
                'birthdate' => Carbon::now()->subYears(random_int(19, 68))->subDays(random_int(0, 365))->toDateString(),
                'password' => self::DEMO_PASSWORD,
                'must_change_password' => $i % 5 === 0,
                'registration_source' => $i % 3 === 0 ? 'self_registered' : 'manual_admin',
                'created_by_admin_user_id' => AdminUser::query()->inRandomOrder()->value('id'),
                'status' => $i === 30 ? 'blocked' : 'active',
            ]);

            $patients[] = $patient;
        }

        // Primer paciente de demo con credenciales conocidas y fijas, útil
        // para correr manualmente el flujo de pruebas funcionales.
        $patients[0]->update([
            'first_name' => 'Paciente',
            'last_name' => 'Demo',
            'email' => 'paciente.demo@'.self::EMAIL_DOMAIN,
            'document_number' => '900000001',
            'password' => self::DEMO_PASSWORD,
            'must_change_password' => false,
            'status' => 'active',
        ]);

        return $patients;
    }

    private function createSalesFor(Patient $patient, $admins, $procedures, int $index): void
    {
        $saleCount = $index === 0 ? 4 : random_int(1, 4);
        $exchangeRate = PointRates::accrualCopPerPoint();

        for ($s = 0; $s < $saleCount; $s++) {
            $saleDate = Carbon::now()->subMonths(random_int(0, 9))->subDays(random_int(0, 27));
            $amount = random_int(3, 60) * 100000; // 300.000 a 6.000.000 COP

            Carbon::setTestNow($saleDate);

            try {
                $sale = Sale::create([
                    'patient_id' => $patient->id,
                    'admin_user_id' => $admins->random()->id,
                    'procedure_id' => random_int(1, 10) <= 7 ? $procedures->random()?->id : null,
                    'amount_cop' => $amount,
                    'exchange_rate_applied' => $exchangeRate,
                    'points_awarded' => PointRates::pointsEarnedFor($amount, $exchangeRate),
                    'sale_date' => $saleDate->toDateString(),
                    'notes' => null,
                ]);

                app(PointAccrualService::class)->accrueForSale($sale);
            } finally {
                Carbon::setTestNow();
            }
        }
    }

    private function createRedemptionsFor(Patient $patient, $rewards): void
    {
        $redemptionCount = random_int(1, 2);

        for ($r = 0; $r < $redemptionCount; $r++) {
            $balance = $patient->pointsBalance();

            $affordable = $rewards->filter(fn (Reward $reward) => $reward->pointsEquivalent() <= $balance && $reward->pointsEquivalent() > 0);

            if ($affordable->isEmpty()) {
                return;
            }

            $reward = $affordable->random();
            $total = $reward->pointsEquivalent();

            // ~60% paga 100% con puntos, ~40% hace un pago mixto.
            $pointsToUse = random_int(1, 10) <= 6
                ? $total
                : random_int((int) ceil($total * 0.1), $total - 1 > 0 ? $total - 1 : $total);

            $redemptionDate = Carbon::now()->subDays(random_int(1, 150));
            Carbon::setTestNow($redemptionDate);

            try {
                $outcome = app(RedemptionService::class)->checkout($patient, $reward, $pointsToUse);
                $redemption = $outcome->redemption;

                if ($outcome->requiresPayment()) {
                    $service = app(RedemptionService::class);
                    $roll = random_int(1, 100);

                    if ($roll <= 50) {
                        $service->approve($redemption);
                    } elseif ($roll <= 75) {
                        $service->reject($redemption);
                    }
                    // el resto queda en pending_payment, tal como en producción.
                }

                // Variedad adicional: algunas redenciones aprobadas quedan "fulfilled".
                if ($redemption->refresh()->status === 'approved' && random_int(1, 100) <= 40) {
                    $redemption->update(['status' => 'fulfilled']);
                }
            } catch (InvalidCheckoutException) {
                // Si el cálculo de límites rechaza la combinación, simplemente se omite.
            } finally {
                Carbon::setTestNow();
            }
        }
    }
}
