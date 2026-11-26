<?php

namespace App\Mail;

use App\Models\Patient;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PointsEarnedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Patient $patient, public int $points, public int $balance) {}

    public function build(): self
    {
        return $this->subject('Acumulaste '.number_format($this->points, 0, ',', '.').' puntos — Plan de Fidelización Harker Lloreda')
            ->view('emails.points-earned')
            ->text('emails.points-earned-text');
    }
}
