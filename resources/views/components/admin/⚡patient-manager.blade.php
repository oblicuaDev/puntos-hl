<?php

use App\Models\AdminUser;
use App\Models\Patient;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $search = '';

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
            ->orderByDesc('id')
            ->limit(50)
            ->get();
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
        unset($this->patients);
    }

    public function toggleStatus(int $id): void
    {
        $patient = Patient::findOrFail($id);
        $patient->update(['status' => $patient->status === 'active' ? 'blocked' : 'active']);
        unset($this->patients);
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'document_number', 'first_name', 'last_name', 'email', 'phone', 'address', 'city', 'birthdate']);
        $this->document_type = 'CC';
    }
};
?>

<div class="space-y-6">
    <form wire:submit="save" class="bg-white rounded-xl shadow p-6 space-y-4 max-w-2xl">
        <h2 class="font-semibold">{{ $editingId ? 'Editar paciente' : 'Registrar paciente' }}</h2>

        @if (! $editingId)
            <p class="text-xs text-muted">La contraseña inicial del paciente será su número de documento. Se le pedirá cambiarla en su primer ingreso al portal.</p>
        @endif

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium">Tipo de documento</label>
                <select wire:model="document_type" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
                    <option value="CC">Cédula de ciudadanía</option>
                    <option value="CE">Cédula de extranjería</option>
                    <option value="TI">Tarjeta de identidad</option>
                    <option value="PAS">Pasaporte</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium">Número de documento</label>
                <input type="text" wire:model="document_number" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
                @error('document_number') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">Nombres</label>
                <input type="text" wire:model="first_name" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
                @error('first_name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">Apellidos</label>
                <input type="text" wire:model="last_name" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
                @error('last_name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">Correo</label>
                <input type="email" wire:model="email" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
                @error('email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">Teléfono</label>
                <input type="text" wire:model="phone" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-medium">Dirección</label>
                <input type="text" wire:model="address" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium">Ciudad</label>
                <input type="text" wire:model="city" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium">Fecha de nacimiento</label>
                <input type="date" wire:model="birthdate" class="mt-1 w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none">
            </div>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="rounded-full bg-azul text-white px-5 py-2 text-sm font-medium border-2 border-transparent transition-all duration-500 hover:bg-white hover:text-azul hover:border-azul">
                {{ $editingId ? 'Guardar cambios' : 'Registrar paciente' }}
            </button>
            @if ($editingId)
                <button type="button" wire:click="cancel" class="rounded px-4 py-2 text-sm text-muted">Cancelar</button>
            @endif
        </div>
    </form>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="p-4 border-b">
            <input type="text" wire:model.live.debounce.400ms="search" placeholder="Buscar por documento o nombre..." class="w-full rounded px-3 py-2 border border-black/15 focus:border-azul focus:ring-1 focus:ring-azul focus:outline-none text-sm">
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left">
                <tr>
                    <th class="p-3">Documento</th>
                    <th class="p-3">Nombre</th>
                    <th class="p-3">Teléfono</th>
                    <th class="p-3">Estado</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->patients as $patient)
                    <tr wire:key="patient-{{ $patient->id }}" class="border-t">
                        <td class="p-3">{{ $patient->document_number }}</td>
                        <td class="p-3">{{ $patient->fullName() }}</td>
                        <td class="p-3">{{ $patient->phone }}</td>
                        <td class="p-3">{{ $patient->status === 'active' ? 'Activo' : 'Bloqueado' }}</td>
                        <td class="p-3 text-right space-x-2">
                            <button wire:click="edit({{ $patient->id }})" class="text-azul">Editar</button>
                            <button wire:click="toggleStatus({{ $patient->id }})" class="text-amber-600">
                                {{ $patient->status === 'active' ? 'Bloquear' : 'Activar' }}
                            </button>
                        </td>
                    </tr>
                @endforeach
                @if ($this->patients->isEmpty())
                    <tr><td colspan="5" class="p-3 text-muted">Sin pacientes todavía.</td></tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
