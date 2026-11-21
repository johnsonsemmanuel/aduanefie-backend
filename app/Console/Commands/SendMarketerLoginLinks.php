<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketerAuthController;
use App\Models\Admin;
use Illuminate\Console\Command;

class SendMarketerLoginLinks extends Command
{
    protected $signature = 'marketers:send-login-links
        {--id= : Send to a single marketer admin ID}
        {--offset=0 : Starting offset for batch sending}
        {--limit=25 : Maximum marketers per batch}
        {--dry-run : Preview recipients without sending SMS/Email}';

    protected $description = 'Send one-click signed login links via SMS and Email to existing Role-5 Remote Marketers';

    public function handle(): int
    {
        $roleId = (int) config('marketer.role_id', 5);
        $query = Admin::where('role_id', $roleId)->orderBy('id');

        if ($id = $this->option('id')) {
            $query->where('id', (int) $id);
        } else {
            $query->skip(max(0, (int) $this->option('offset')))
                  ->take(max(1, (int) $this->option('limit')));
        }

        $marketers = $query->get();
        if ($marketers->isEmpty()) {
            $this->warn('No matching remote marketers found.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $smsCount = 0;
        $emailCount = 0;

        foreach ($marketers as $m) {
            if ($dryRun) {
                $this->line("[DRY RUN] #{$m->id} {$m->f_name} {$m->l_name} | {$m->email} | {$m->phone} | {$m->marketer_code}");
                continue;
            }

            $res = MarketerAuthController::sendReengagementNotice($m, 168);
            if ($res['sms']) {
                $smsCount++;
            }
            if ($res['email']) {
                $emailCount++;
            }
            $this->line("Sent #{$m->id} ({$m->email}) -> SMS:" . ($res['sms'] ? 'OK' : 'FAIL') . ' Email:' . ($res['email'] ? 'OK' : 'FAIL'));
        }

        if (! $dryRun) {
            $this->info("Completed {$marketers->count()} marketer(s): {$smsCount} SMS, {$emailCount} Email.");
        }

        return self::SUCCESS;
    }
}
