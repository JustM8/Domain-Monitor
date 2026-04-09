<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DomainReportMail extends Mailable
{
    use SerializesModels;

    public $checks;

    public function __construct($checks)
    {
        $this->checks = $checks;
    }

    public function build()
    {
        return $this
            ->subject('Domain Check Report')
            ->view('emails.domain-report')
            ->with([
                'checks' => $this->checks
            ]);
    }
}
