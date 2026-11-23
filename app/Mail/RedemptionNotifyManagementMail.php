<?php

namespace App\Mail;

use App\Models\Redemption;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RedemptionNotifyManagementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Redemption $redemption) {}

    public function build(): self
    {
        return $this->subject('Nuevo canje de puntos — '.$this->redemption->code)
            ->view('emails.redemption-notify-management', ['redemption' => $this->redemption])
            ->text('emails.redemption-notify-management-text', ['redemption' => $this->redemption]);
    }
}
