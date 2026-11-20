<?php

namespace App\Mail;

use App\Models\Redemption;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RedemptionConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Redemption $redemption) {}

    public function build(): self
    {
        return $this->subject('Confirmación de tu canje — Plan de Fidelización Harker Lloreda')
            ->markdown('emails.redemption-confirmed', ['redemption' => $this->redemption]);
    }
}
