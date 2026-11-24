<?php

namespace App\Mail;

use App\Models\MarketerApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MarketerApplicationReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public MarketerApplication $application)
    {
    }

    public function build()
    {
        return $this->subject('We received your Regional Marketer application')
            ->view('email-templates.marketer.application-received', [
                'name' => $this->application->full_name,
                'region' => $this->application->region,
            ]);
    }
}
