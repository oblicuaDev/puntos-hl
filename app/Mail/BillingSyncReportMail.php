<?php

namespace App\Mail;

use App\Models\BillingSyncRun;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BillingSyncReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public BillingSyncRun $run) {}

    public function build(): self
    {
        $subject = $this->run->error
            ? 'Error en la carga diaria de facturación'
            : 'Carga diaria de facturación: '.$this->run->rows_processed.' acreditadas, '.$this->run->rows_pending.' pendientes';

        return $this->subject($subject)
            ->view('emails.billing-sync-report', ['run' => $this->run])
            ->text('emails.billing-sync-report-text', ['run' => $this->run]);
    }
}
