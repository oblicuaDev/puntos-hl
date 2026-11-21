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
    <form wire:submit="save" class="bg-white rounded-brand shadow-sm ring-1 ring-black/5 p-6 space-y-4 max-w-xl">
        <x-card-header icon="o-banknotes">Registrar venta</x-card-header>

        @if ($successMessage)
            <p class="text-sm text-emerald-600 flex items-center gap-1.5">
                @svg('heroicon-s-check-circle', 'h-4 w-4')
                {{ $successMessage }}
            </p>
        @endif

        <div>
            <label class="block text-sm font-medium">Paciente</label>

            @if ($selectedPatientLabel)
                <div class="mt-1.5 flex items-center justify-between rounded-xl border border-black/10 bg-white px-3 py-2.5 text-sm">
                    <span>{{ $selectedPatientLabel }}</span>
                    <button type="button" wire:click="clearPatient" class="rounded-lg px-3 py-1.5 font-medium text-red-600 hover:bg-red-50 transition-colors">Cambiar</button>
                </div>
            @else
                <input type="text" wire:model.live.debounce.300ms="patientSearch" placeholder="Buscar por documento o nombre..." class="mt-1.5 w-full rounded-xl px-3 py-2.5 border border-black/10 bg-white focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">

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
            <select wire:model="procedure_id" class="mt-1.5 w-full rounded-xl px-3 py-2.5 border border-black/10 bg-white focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">
                <option value="">Sin procedimiento específico</option>
                @foreach ($this->procedures as $procedure)
                    <option value="{{ $procedure->id }}">{{ $procedure->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium">Valor de la venta (COP)</label>
            <input type="number" wire:model="amount_cop" class="mt-1.5 w-full rounded-xl px-3 py-2.5 border border-black/10 bg-white focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">
            @error('amount_cop') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <p class="text-xs text-muted mt-1">Tasa vigente: {{ number_format(setting('cop_per_point', 100), 0, ',', '.') }} COP por punto.</p>
        </div>

        <div>
            <label class="block text-sm font-medium">Fecha de la venta</label>
            <input type="date" wire:model="sale_date" class="mt-1.5 w-full rounded-xl px-3 py-2.5 border border-black/10 bg-white focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium">Notas</label>
            <textarea wire:model="notes" class="mt-1.5 w-full rounded-xl px-3 py-2.5 border border-black/10 bg-white focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none"></textarea>
        </div>

        <x-submit-button target="save" loading-text="Registrando…" class="rounded-xl bg-azul text-white px-5 py-2.5 text-sm font-semibold transition-colors hover:bg-azul-dark">Registrar venta</x-submit-button>
    </form>

    <div class="bg-white rounded-brand shadow-sm ring-1 ring-black/5 overflow-hidden"><table class="w-full text-sm">
        <thead class="text-left text-[11px] uppercase tracking-wider text-muted bg-[#F7F8FA]">
            <tr>
                <th class="px-5 py-3 font-semibold">Fecha</th>
                <th class="px-5 py-3 font-semibold">Paciente</th>
                <th class="px-5 py-3 font-semibold">Procedimiento</th>
                <th class="px-5 py-3 font-semibold">Valor</th>
                <th class="px-5 py-3 font-semibold">Puntos</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($this->recentSales as $sale)
                <tr wire:key="sale-{{ $sale->id }}" class="border-t border-black/5 hover:bg-[#FAFBFC] transition-colors">
                    <td class="px-5 py-3">{{ $sale->sale_date->format('d/m/Y') }}</td>
                    <td class="px-5 py-3">{{ $sale->patient?->fullName() }}</td>
                    <td class="px-5 py-3">{{ $sale->procedure?->name ?? '—' }}</td>
                    <td class="px-5 py-3">${{ number_format($sale->amount_cop, 0, ',', '.') }}</td>
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center gap-1 rounded-full bg-dermo/40 text-marino px-2 py-0.5 text-xs font-medium">
                            @svg('heroicon-s-sparkles', 'h-3 w-3')
                            +{{ number_format($sale->points_awarded) }}
                        </span>
                    </td>
                </tr>
            @endforeach
            @if ($this->recentSales->isEmpty())
                <x-empty-state-row :colspan="5" icon="o-banknotes" message="Sin ventas todavía." />
            @endif
        </tbody>
    </table></div>
</div>
