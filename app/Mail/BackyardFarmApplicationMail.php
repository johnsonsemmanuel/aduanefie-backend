<?php

namespace App\Mail;

use App\Models\BackyardFarmApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Backyard farm application notification emails.
 *
 * Renders Blade templates under resources/views/email-templates/backyard/
 * so backyard farmers receive branded, consistent emails at each stage of
 * the verification workflow.
 */
class BackyardFarmApplicationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $template;
    public $data;

    /**
     * @param  string  $template  Blade view name (without .blade.php)
     * @param  array  $data  Template variables
     */
    public function __construct(string $template, array $data = [])
    {
        $this->template = $template;
        $this->data = $data;
    }

    public function build()
    {
        $subject = match ($this->template) {
            'email-templates.backyard.application-submitted' => 'Your backyard farm application has been received',
            'email-templates.backyard.application-verified' => 'Your backyard farm has been verified',
            'email-templates.backyard.application-approved' => 'Your backyard farm is approved 🎉',
            'email-templates.backyard.application-rejected' => 'Your backyard farm application was not approved',
            default => 'Backyard Farm Application Update',
        };

        return $this->subject($subject)
            ->view($this->template, $this->data);
    }
}
