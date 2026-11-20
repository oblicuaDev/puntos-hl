<?php

namespace App\Console\Commands;

use App\Models\Redemption;
use App\Services\Points\FifoPointConsumer;
use Illuminate\Console\Command;

class CancelAbandonedCheckouts extends Command
{
    protected $signature = 'checkout:cancel-abandoned';

    protected $description = 'Cancela canjes mixtos que quedaron en pending_payment más allá del timeout y libera los puntos reservados.';

    public function __construct(private FifoPointConsumer $pointConsumer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $timeoutMinutes = (int) setting('abandoned_checkout_timeout_minutes', 30);

        $abandoned = Redemption::where('status', 'pending_payment')
            ->where('created_at', '<=', now()->subMinutes($timeoutMinutes))
            ->get();

        foreach ($abandoned as $redemption) {
            $redemption->update(['status' => 'cancelled']);
            $this->pointConsumer->reverse($redemption);
        }

        $this->info("Canjes abandonados cancelados: {$abandoned->count()}");

        return self::SUCCESS;
    }
}
