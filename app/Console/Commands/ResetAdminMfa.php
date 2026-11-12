<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;

/**
 * Lockout recovery for a lost authenticator and lost recovery codes.
 *
 * Requires shell access to the server, which is the point: nobody can clear a
 * second factor over the web, so an attacker with a stolen password cannot use
 * this path. The next login re-enters mandatory enrolment.
 */
class ResetAdminMfa extends Command
{
    protected $signature = 'admin:mfa-reset
                            {email : Email address of the admin account}
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Clear two-factor authentication for an admin account';

    public function handle(): int
    {
        $admin = Admin::where('email', $this->argument('email'))->first();

        if (!$admin) {
            $this->error('No admin account with that email address.');

            return self::FAILURE;
        }

        if (!$this->option('force')
            && !$this->confirm("Clear two-factor authentication for {$admin->email} (role {$admin->role_id})?")) {
            $this->line('Nothing changed.');

            return self::FAILURE;
        }

        $admin->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();

        $this->info("Two-factor authentication cleared for {$admin->email}.");
        $this->line('The next login will require enrolment with a new authenticator.');

        return self::SUCCESS;
    }
}
