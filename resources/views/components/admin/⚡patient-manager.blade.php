<?php

use App\Models\AdminUser;
use App\Models\Patient;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $search = '';

    public string $statusFilter = 'all';

    public ?int $editingId = null;

    public string $document_type = 'CC';

    public string $document_number = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public string $city = '';

    public ?string $birthdate = null;

    #[Computed]
    public function patients()
    {
        return Patient::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('document_number', 'like', "%{$this->search}%")
                        ->orWhere('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%");
                });
            })
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function statusCounts(): array
    {
        return [
            'all' => Patient::count(),
            'active' => Patient::where('status', 'active')->count(),
            'blocked' => Patient::where('status', 'blocked')->count(),
        ];
    }

    protected function rules(): array
    {
        return [
            'document_type' => 'required|in:CC,CE,TI,PAS',
            'document_number' => 'required|string|max:30|unique:patients,document_number,'.($this->editingId ?: 'NULL'),
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:patients,email,'.($this->editingId ?: 'NULL'),
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'birthdate' => 'nullable|date',
        ];
    }

    public function edit(int $id): void
    {
        $patient = Patient::findOrFail($id);

        $this->editingId = $patient->id;
        $this->document_type = $patient->document_type;
        $this->document_number = $patient->document_number;
        $this->first_name = $patient->first_name;
        $this->last_name = $patient->last_name;
        $this->email = (string) $patient->email;
        $this->phone = (string) $patient->phone;
        $this->address = (string) $patient->address;
        $this->city = (string) $patient->city;
        $this->birthdate = $patient->birthdate?->format('Y-m-d');
    }

    public function save(): void
    {
        $data = $this->validate();
        $data['email'] = $data['email'] ?: null;

        if ($this->editingId) {
            Patient::whereKey($this->editingId)->update($data);
        } else {
            // Contraseña inicial = cédula (el admin/asesor ya la conoce, no se expone en ningún lado).
            // must_change_password obliga a cambiarla en el primer login del portal.
            $data['password'] = $data['document_number'];
            $data['must_change_password'] = true;
            $data['registration_source'] = 'manual_admin';
            $data['created_by_admin_user_id'] = Auth::guard('admin')->id();
            $data['status'] = 'active';

            Patient::create($data);
        }

        $this->cancel();
        unset($this->patients, $this->statusCounts);
    }

    public function toggleStatus(int $id): void
    {
        $patient = Patient::findOrFail($id);
        $patient->update(['status' => $patient->status === 'active' ? 'blocked' : 'active']);
        unset($this->patients, $this->statusCounts);
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'document_number', 'first_name', 'last_name', 'email', 'phone', 'address', 'city', 'birthdate']);
        $this->document_type = 'CC';
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold text-marino tracking-tight">Pacientes</h1>
            <p class="text-sm text-muted mt-1">Registra pacientes, edita sus datos y gestiona el acceso al portal.</p>
        </div>
    </div>

    <form wire:submit="save" class="bg-white rounded-brand shadow-sm ring-1 ring-black/5 p-6 space-y-5">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-marino flex items-center gap-2">
                @svg($editingId ? 'heroicon-o-pencil-square' : 'heroicon-o-user-plus', 'h-4 w-4 text-azul')
                {{ $editingId ? 'Editar paciente' : 'Registrar paciente' }}
            </h2>
            @if (! $editingId)
                <p class="hidden lg:block text-xs text-muted max-w-sm text-right">La contraseña inicial será su número de documento. Deberá cambiarla en su primer ingreso.</p>
            @endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-ink">Tipo de documento</label>
                <select wire:model="document_type" class="mt-1.5 w-full rounded-xl px-3 h-11 border border-black/10 bg-white focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">
                    <option value="CC">Cédula de ciudadanía</option>
                    <option value="CE">Cédula de extranjería</option>
                    <option value="TI">Tarjeta de identidad</option>
                    <option value="PAS">Pasaporte</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-ink">Número de documento</label>
                <input type="text" wire:model="document_number" class="mt-1.5 w-full rounded-xl px-3 h-11 border border-black/10 focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">
                @error('document_number') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-ink">Nombres</label>
                <input type="text" wire:model="first_name" class="mt-1.5 w-full rounded-xl px-3 h-11 border border-black/10 focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">
                @error('first_name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-ink">Apellidos</label>
                <input type="text" wire:model="last_name" class="mt-1.5 w-full rounded-xl px-3 h-11 border border-black/10 focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">
                @error('last_name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-ink">Correo</label>
                <input type="email" wire:model="email" class="mt-1.5 w-full rounded-xl px-3 h-11 border border-black/10 focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">
                @error('email') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-ink">Teléfono</label>
                <input type="text" wire:model="phone" class="mt-1.5 w-full rounded-xl px-3 h-11 border border-black/10 focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-ink">Dirección</label>
                <input type="text" wire:model="address" class="mt-1.5 w-full rounded-xl px-3 h-11 border border-black/10 focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-ink">Ciudad</label>
                <input type="text" wire:model="city" class="mt-1.5 w-full rounded-xl px-3 h-11 border border-black/10 focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-ink">Fecha de nacimiento</label>
                <input type="date" wire:model="birthdate" class="mt-1.5 w-full rounded-xl px-3 h-11 border border-black/10 focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none">
            </div>
        </div>

        <div class="flex items-center gap-3 pt-1">
            <x-submit-button target="save" loading-text="Guardando…" class="rounded-xl bg-azul text-white px-5 h-11 text-sm font-semibold transition-colors hover:bg-azul-dark">
                {{ $editingId ? 'Guardar cambios' : 'Registrar paciente' }}
            </x-submit-button>
            @if ($editingId)
                <button type="button" wire:click="cancel" class="rounded-xl px-4 h-11 text-sm font-medium text-muted hover:text-ink hover:bg-black/5">Cancelar</button>
            @endif
        </div>
    </form>

    <div class="bg-white rounded-brand shadow-sm ring-1 ring-black/5 overflow-hidden">
        <div class="p-4 border-b border-black/5 flex flex-col md:flex-row md:items-center gap-3">
            <div class="flex gap-2">
                @foreach ([['all', 'Todos'], ['active', 'Activos'], ['blocked', 'Bloqueados']] as [$value, $label])
                    <button wire:click="$set('statusFilter', '{{ $value }}')" class="rounded-full px-3.5 py-1.5 text-xs font-semibold whitespace-nowrap transition-colors {{ $statusFilter === $value ? 'bg-marino text-white' : 'bg-white border border-black/10 text-ink hover:border-marino' }}">
                        {{ $label }} · {{ number_format($this->statusCounts[$value]) }}
                    </button>
                @endforeach
            </div>
            <div class="relative flex-1 max-w-md md:ml-auto">
                <span wire:loading.remove wire:target="search,statusFilter" class="absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none">
                    @svg('heroicon-o-magnifying-glass', 'h-4 w-4 text-steel')
                </span>
                <svg wire:loading wire:target="search,statusFilter" class="animate-spin h-4 w-4 text-azul absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <input type="text" wire:model.live.debounce.400ms="search" placeholder="Buscar por documento o nombre" aria-label="Buscar pacientes" class="w-full rounded-xl pl-10 pr-3 h-10 border border-black/10 focus:border-azul focus:ring-2 focus:ring-azul/20 focus:outline-none text-sm">
            </div>
        </div>

        <div wire:loading.class="opacity-40" wire:target="search,statusFilter" class="overflow-x-auto transition-opacity">
            <table class="w-full text-sm">
                <thead class="text-left text-[11px] uppercase tracking-wider text-muted bg-[#F7F8FA]">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Paciente</th>
                        <th class="px-5 py-3 font-semibold">Documento</th>
                        <th class="px-5 py-3 font-semibold">Teléfono</th>
                        <th class="px-5 py-3 font-semibold">Puntos</th>
                        <th class="px-5 py-3 font-semibold">Estado</th>
                        <th class="px-5 py-3 font-semibold text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5">
                    @foreach ($this->patients as $patient)
                        <tr wire:key="patient-{{ $patient->id }}" class="hover:bg-[#FAFBFC] transition-colors">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="h-9 w-9 rounded-full bg-azul/10 text-azul text-xs font-semibold flex items-center justify-center shrink-0">{{ mb_strtoupper(mb_substr($patient->first_name, 0, 1).mb_substr($patient->last_name, 0, 1)) }}</span>
                                    <div class="min-w-0">
                                        <p class="font-medium text-ink truncate">{{ $patient->fullName() }}</p>
                                        @if ($patient->email)
                                            <p class="text-xs text-muted truncate">{{ $patient->email }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3 tabular-nums text-ink/80">{{ $patient->document_number }}</td>
                            <td class="px-5 py-3 tabular-nums text-ink/80">{{ $patient->phone ?: '—' }}</td>
                            <td class="px-5 py-3 tabular-nums font-semibold text-azul">{{ number_format($patient->pointsBalance()) }}</td>
                            <td class="px-5 py-3">
                                @if ($patient->status === 'active')
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 text-emerald-700 px-2.5 py-1 text-xs font-medium"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Activo</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-black/5 text-muted px-2.5 py-1 text-xs font-medium"><span class="h-1.5 w-1.5 rounded-full bg-steel"></span>Bloqueado</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button wire:click="edit({{ $patient->id }})" class="rounded-lg px-3 py-1.5 text-sm font-medium text-azul hover:bg-azul/10 transition-colors">Editar</button>
                                    <button wire:click="toggleStatus({{ $patient->id }})" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $patient->status === 'active' ? 'text-amber-700 hover:bg-amber-50' : 'text-emerald-700 hover:bg-emerald-50' }} transition-colors">
                                        {{ $patient->status === 'active' ? 'Bloquear' : 'Activar' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    @if ($this->patients->isEmpty())
                        <x-empty-state-row :colspan="6" icon="o-users" message="Sin pacientes todavía." />
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
