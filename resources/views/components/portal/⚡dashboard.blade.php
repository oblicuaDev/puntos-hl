<?php

use App\Models\PointBatch;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function patient()
    {
        return Auth::guard('patient')->user();
    }

    #[Computed]
    public function expiringSoon()
    {
        return PointBatch::where('patient_id', $this->patient->id)
            ->activeAndValid()
            ->where('expires_at', '<=', now()->addDays(60))
            ->orderBy('expires_at')
            ->get();
    }

    #[Computed]
    public function movements()
    {
        return $this->patient->pointMovements()->latest('occurred_at')->limit(30)->get();
    }
};
?>

<div class="space-y-6">
    <div class="bg-gray-900 text-white rounded-xl p-6">
        <p class="text-sm opacity-80">Tu saldo de puntos</p>
        <p class="text-3xl font-semibold">{{ number_format($this->patient->pointsBalance()) }}</p>
    </div>

    @if ($this->expiringSoon->isNotEmpty())
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
            <p class="text-sm font-medium text-amber-800">Puntos por vencer</p>
            <ul class="mt-2 space-y-1 text-sm text-amber-700">
                @foreach ($this->expiringSoon as $batch)
                    <li>{{ number_format($batch->points_remaining) }} puntos vencen el {{ $batch->expires_at->format('d/m/Y') }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow p-4">
        <h2 class="font-semibold mb-3">Historial</h2>
        <ul class="divide-y text-sm">
            @foreach ($this->movements as $movement)
                <li class="py-2 flex justify-between">
                    <div>
                        <p>{{ $movement->description }}</p>
                        <p class="text-gray-400 text-xs">{{ $movement->occurred_at->format('d/m/Y H:i') }}</p>
                    </div>
                    <span class="{{ $movement->points >= 0 ? 'text-emerald-600' : 'text-red-600' }} font-medium">
                        {{ $movement->points >= 0 ? '+' : '' }}{{ number_format($movement->points) }}
                    </span>
                </li>
            @endforeach
            @if ($this->movements->isEmpty())
                <li class="py-2 text-gray-500">Sin movimientos todavía.</li>
            @endif
        </ul>
    </div>
</div>
