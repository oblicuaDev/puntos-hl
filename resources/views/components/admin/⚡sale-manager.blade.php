<?php

use App\Models\Patient;
use App\Models\Procedure;
use App\Models\Sale;
use App\Services\Points\PointAccrualService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    public string $patientSearch = '';

    public ?int $patient_id = null;

    public ?string $selectedPatientLabel = null;

    #[Validate('nullable|exists:procedures,id')]
    public ?int $procedure_id = null;

    #[Validate('required|integer|min:1')]
    public ?int $amount_cop = null;

    #[Validate('required|date')]
    public string $sale_date;

    #[Validate('nullable|string|max:500')]
    public string $notes = '';

    public ?string $successMessage = null;

    public function mount(): void
    {
        $this->sale_date = now()->format('Y-m-d');
    }

    #[Computed]
    public function patientMatches()
    {
        if (strlen($this->patientSearch) < 2 || $this->patient_id) {
            return collect();
        }

        return Patient::where('document_number', 'like', "%{$this->patientSearch}%")
            ->orWhere('first_name', 'like', "%{$this->patientSearch}%")
            ->orWhere('last_name', 'like', "%{$this->patientSearch}%")
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function procedures()
    {
        return Procedure::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function recentSales()
    {
        return Sale::with('patient', 'procedure')->latest()->limit(20)->get();
    }

    public function selectPatient(int $id): void
    {
        $patient = Patient::findOrFail($id);
        $this->patient_id = $patient->id;
        $this->selectedPatientLabel = $patient->fullName().' — '.$patient->document_number;
        $this->patientSearch = '';
    }

    public function clearPatient(): void
    {
        $this->patient_id = null;
        $this->selectedPatientLabel = null;
    }

    public function save(): void
    {
        $this->validate();

        if (! $this->patient_id) {
            $this->addError('patient_id', 'Selecciona un paciente.');

            return;
        }

        $exchangeRate = (float) setting('cop_per_point', 100);
        $pointsAwarded = (int) round($this->amount_cop / max($exchangeRate, 0.01));

        $sale = DB::transaction(function () use ($exchangeRate, $pointsAwarded) {
            $sale = Sale::create([
                'patient_id' => $this->patient_id,
                'admin_user_id' => Auth::guard('admin')->id(),
                'procedure_id' => $this->procedure_id,
                'amount_cop' => $this->amount_cop,
                'exchange_rate_applied' => $exchangeRate,
                'points_awarded' => $pointsAwarded,
                'sale_date' => $this->sale_date,
                'notes' => $this->notes ?: null,
            ]);

            app(PointAccrualService::class)->accrueForSale($sale);

            return $sale;
        });

        $this->successMessage = "Venta registrada: {$pointsAwarded} puntos acreditados.";

        $this->reset(['procedure_id', 'amount_cop', 'notes']);
        $this->clearPatient();
        $this->sale_date = now()->format('Y-m-d');

        unset($this->recentSales);
    }
};
?>

<div class="space-y-6">
    <form wire:submit="save" class="bg-white rounded-xl shadow p-6 space-y-4 max-w-xl">
        <h2 class="font-semibold">Registrar venta</h2>

        @if ($successMessage)
            <p class="text-sm text-emerald-600">{{ $successMessage }}</p>
        @endif

        <div>
            <label class="block text-sm font-medium">Paciente</label>

            @if ($selectedPatientLabel)
                <div class="mt-1 flex items-center justify-between rounded border border-gray-300 px-3 py-2 text-sm">
                    <span>{{ $selectedPatientLabel }}</span>
                    <button type="button" wire:click="clearPatient" class="text-red-600">Cambiar</button>
                </div>
            @else
                <input type="text" wire:model.live.debounce.300ms="patientSearch" placeholder="Buscar por documento o nombre..." class="mt-1 w-full rounded border-gray-300">

                @if ($this->patientMatches->isNotEmpty())
                    <ul class="mt-1 border rounded divide-y text-sm">
                        @foreach ($this->patientMatches as $match)
                            <li wire:key="match-{{ $match->id }}">
                                <button type="button" wire:click="selectPatient({{ $match->id }})" class="w-full text-left px-3 py-2 hover:bg-gray-50">
                                    {{ $match->fullName() }} — {{ $match->document_number }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endif
            @error('patient_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Procedimiento (opcional)</label>
            <select wire:model="procedure_id" class="mt-1 w-full rounded border-gray-300">
                <option value="">Sin procedimiento específico</option>
                @foreach ($this->procedures as $procedure)
                    <option value="{{ $procedure->id }}">{{ $procedure->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium">Valor de la venta (COP)</label>
            <input type="number" wire:model="amount_cop" class="mt-1 w-full rounded border-gray-300">
            @error('amount_cop') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <p class="text-xs text-gray-500 mt-1">Tasa vigente: {{ number_format(setting('cop_per_point', 100), 0, ',', '.') }} COP por punto.</p>
        </div>

        <div>
            <label class="block text-sm font-medium">Fecha de la venta</label>
            <input type="date" wire:model="sale_date" class="mt-1 w-full rounded border-gray-300">
        </div>

        <div>
            <label class="block text-sm font-medium">Notas</label>
            <textarea wire:model="notes" class="mt-1 w-full rounded border-gray-300"></textarea>
        </div>

        <button type="submit" class="rounded bg-gray-900 text-white px-4 py-2 text-sm font-medium">Registrar venta</button>
    </form>

    <table class="w-full bg-white rounded-xl shadow overflow-hidden text-sm">
        <thead class="bg-gray-50 text-left">
            <tr>
                <th class="p-3">Fecha</th>
                <th class="p-3">Paciente</th>
                <th class="p-3">Procedimiento</th>
                <th class="p-3">Valor</th>
                <th class="p-3">Puntos</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($this->recentSales as $sale)
                <tr wire:key="sale-{{ $sale->id }}" class="border-t">
                    <td class="p-3">{{ $sale->sale_date->format('d/m/Y') }}</td>
                    <td class="p-3">{{ $sale->patient?->fullName() }}</td>
                    <td class="p-3">{{ $sale->procedure?->name ?? '—' }}</td>
                    <td class="p-3">${{ number_format($sale->amount_cop, 0, ',', '.') }}</td>
                    <td class="p-3">{{ $sale->points_awarded }}</td>
                </tr>
            @endforeach
            @if ($this->recentSales->isEmpty())
                <tr><td colspan="5" class="p-3 text-gray-500">Sin ventas todavía.</td></tr>
            @endif
        </tbody>
    </table>
</div>
