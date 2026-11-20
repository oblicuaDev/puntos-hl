<?php

namespace App\Listeners;

use App\Events\RedemptionApproved;
use App\Mail\RedemptionConfirmedMail;
use App\Mail\RedemptionNotifyManagementMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendRedemptionApprovedMail implements ShouldQueue
{
    public function handle(RedemptionApproved $event): void
    {
        $redemption = $event->redemption;

        if ($redemption->patient->email) {
            Mail::to($redemption->patient->email)->queue(new RedemptionConfirmedMail($redemption));
        }

        Mail::to(setting('management_notification_email', 'gerencia@harkerlloreda.com'))
            ->queue(new RedemptionNotifyManagementMail($redemption));
    }
}
