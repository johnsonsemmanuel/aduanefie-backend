<?php

namespace App\Services\GroupBuy;

use App\Models\GroupBuy;
use App\Models\GroupBuyParticipant;
use App\Models\User;
use App\CentralLogics\SMS_module;
use Illuminate\Support\Facades\Mail;

/**
 * Notifications for the group buying layer.
 *
 * Every state transition (join, lock, payment, reminder, ship, deliver,
 * cancel, expire) emails and/or SMSes the affected members so the group
 * feels like a connected, living team rather than a silent checkout form.
 *
 * Email uses Mail::raw so it works without a template infrastructure.
 * SMS uses the existing Hubtel module (SMS_module::hubtel).
 * notification_last_sent_at on the participant prevents duplicate reminders.
 */
class GroupBuyNotificationService
{
    public function __construct()
    {
    }

    /**
     * Notify that a group has been created.
     */
    public function groupCreated(GroupBuy $group, ?User $actor = null): void
    {
        $organizer = $group->organizer;
        if ($organizer && $organizer->email) {
            $this->sendEmail(
                $organizer->email,
                'Your group buy is live',
                $this->groupCreatedBody($group, $actor),
            );
        }
        if ($organizer && $organizer->phone) {
            $this->sendSms(
                $organizer->phone,
                'Your group buy for "' . $group->item?->name . '" is live. Share the link to fill it before ' . $group->closes_at?->format('M d, H:i') . '.',
            );
        }
    }

    /**
     * Notify that a member has joined. Goes to the new member, the organiser,
     * and all existing members so everyone sees the group grow.
     */
    public function memberJoined(GroupBuy $group, GroupBuyParticipant $newMember): void
    {
        $recipients = $this->allRecipients($group);
        $recipients[] = $newMember;

        foreach ($recipients as $recipient) {
            $isNew = $recipient instanceof GroupBuyParticipant && $recipient->id === $newMember->id;
            $email = $recipient instanceof GroupBuyParticipant
                ? ($recipient->user?->email ?? null)
                : ($recipient['email'] ?? null);
            $phone = $recipient instanceof GroupBuyParticipant
                ? ($recipient->user?->phone ?? null)
                : ($recipient['phone'] ?? null);

            if ($email) {
                $this->sendEmail(
                    $email,
                    $isNew ? 'You joined a group buy' : 'A new member joined your group buy',
                    $this->memberJoinedBody($group, $newMember, $isNew, $recipient === $newMember),
                );
            }
            if ($phone) {
                $this->sendSms(
                    $phone,
                    $group->item?->name . ' group buy: ' . ($isNew ? 'You joined.' : 'A new member joined.') . ' ' . $group->paidCount() . ' of ' . $group->member_limit . ' members now.',
                );
            }
        }
    }

    /**
     * Notify that the group is locked (full) and checkout is open.
     */
    public function groupLocked(GroupBuy $group): void
    {
        foreach ($this->allRecipients($group) as $recipient) {
            $email = $recipient['email'] ?? null;
            $phone = $recipient['phone'] ?? null;

            if ($email) {
                $this->sendEmail(
                    $email,
                    'Group complete - checkout your share',
                    $this->groupLockedBody($group, $recipient),
                );
            }
            if ($phone) {
                $this->sendSms(
                    $phone,
                    'Your group buy for "' . $group->item?->name . '" is full. Checkout your share before ' . $group->closes_at?->format('M d, H:i') . '.',
                );
            }
        }
    }

    /**
     * Reminder to members who still haven't paid. Rate-limited per participant
     * via notification_last_sent_at so we don't spam.
     */
    public function paymentReminder(GroupBuy $group): void
    {
        foreach ($group->participants()->where('payment_status', 'not_charged')->get() as $participant) {
            $lastSent = $participant->notification_last_sent_at;
            if ($lastSent && $lastSent->diffInHours(now()) < 24) {
                continue;
            }

            $user = $participant->user;
            $email = $user?->email;
            $phone = $user?->phone;

            if ($email) {
                $this->sendEmail(
                    $email,
                    'Your group buy share is still unpaid',
                    $this->paymentReminderBody($group, $participant),
                );
            }
            if ($phone) {
                $this->sendSms(
                    $phone,
                    'You have not yet paid for your share of "' . $group->item?->name . '" group buy. Pay before ' . $group->closes_at?->format('M d, H:i') . ' or the group may expire.',
                );
            }

            $participant->update(['notification_last_sent_at' => now()]);
        }
    }

    /**
     * Notify an individual member that their order has been shipped.
     */
    public function shipped(GroupBuyParticipant $participant): void
    {
        $user = $participant->user;
        if (! $user) {
            return;
        }
        if ($user->email) {
            $this->sendEmail(
                $user->email,
                'Your group buy order is shipped',
                $this->shippedBody($participant),
            );
        }
        if ($user->phone) {
            $this->sendSms(
                $user->phone,
                'Your "' . $participant->groupBuy->item?->name . '" group buy order is on its way.',
            );
        }
    }

    /**
     * Notify an individual member that their order has been delivered.
     */
    public function delivered(GroupBuyParticipant $participant): void
    {
        $user = $participant->user;
        if (! $user) {
            return;
        }
        if ($user->email) {
            $this->sendEmail(
                $user->email,
                'Your group buy order is delivered',
                $this->deliveredBody($participant),
            );
        }
        if ($user->phone) {
            $this->sendSms(
                $user->phone,
                'Your "' . $participant->groupBuy->item?->name . '" group buy order has been delivered.',
            );
        }
    }

    /**
     * Notify that the group was cancelled or expired.
     */
    public function groupReleased(GroupBuy $group, string $reason): void
    {
        $label = $reason === 'cancelled' ? 'cancelled' : 'expired';
        foreach ($this->allRecipients($group) as $recipient) {
            $email = $recipient['email'] ?? null;
            $phone = $recipient['phone'] ?? null;

            if ($email) {
                $this->sendEmail(
                    $email,
                    'Group buy ' . $label,
                    $this->groupReleasedBody($group, $label, $recipient),
                );
            }
            if ($phone) {
                $this->sendSms(
                    $phone,
                    'Your group buy for "' . $group->item?->name . '" was ' . $label . '. No payment was taken.',
                );
            }
        }
    }

    /**
     * All recipients: organiser + participants (deduplicated by email/phone).
     *
     * @return array<int, array{email?:string, phone?:string, user?:User|null}>
     */
    private function allRecipients(GroupBuy $group): array
    {
        $recipients = [];

        if ($group->organizer) {
            $recipients[] = ['user' => $group->organizer, 'email' => $group->organizer->email, 'phone' => $group->organizer->phone];
        }

        foreach ($group->participants as $participant) {
            if ($participant->user && $participant->user->id !== ($group->organizer_id ?? null)) {
                $recipients[] = ['user' => $participant->user, 'email' => $participant->user->email, 'phone' => $participant->user->phone];
            }
        }

        return $recipients;
    }

    private function sendEmail(string $to, string $subject, string $body): void
    {
        if (! $to) {
            return;
        }
        try {
            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });
        } catch (\Throwable $e) {
            // Mail failures must not break the group buy flow.
        }
    }

    private function sendSms(?string $to, string $message): void
    {
        if (! $to) {
            return;
        }
        try {
            SMS_module::hubtel($to, '', $message);
        } catch (\Throwable $e) {
            // SMS failures must not break the group buy flow.
        }
    }

    // ---- Email bodies -----------------------------------------------------

    private function groupCreatedBody(GroupBuy $group, ?User $actor): string
    {
        $name = $actor ? $actor->f_name . ' ' . $actor->l_name : 'You';
        $consent = $group->consent_text ? 'Consent required: ' . e($group->consent_text) : '';

        return "Hello {$name}.\n\n"
            . "Your group buy for \"<strong>" . e($group->item?->name) . "</strong>\" from <strong>" . e($group->store?->name) . "</strong> is now live.\n\n"
            . "Target: GH₵ " . number_format($group->target_value_ghs, 2) . " (" . $group->member_limit . " members, " . $group->share_units . " units each)\n"
            . "Closes: " . ($group->closes_at ? $group->closes_at->format('Y-m-d H:i') : '-') . "\n"
            . ($consent ? $consent . "\n" : '')
            . "Share the group link with friends to fill it. No one is charged until the group is complete.\n\n"
            . "-Aduanefie Team";
    }

    private function memberJoinedBody(GroupBuy $group, GroupBuyParticipant $newMember, bool $isNew, bool $isViewer): string
    {
        $memberName = $newMember->user ? $newMember->user->f_name . ' ' . $newMember->user->l_name : 'A guest';
        $yourself = $isViewer ? 'You' : $memberName;
        $action = $isNew ? 'have joined' : 'joined';
        $consent = $group->consent_text ? 'Consent required: ' . e($group->consent_text) : '';

        return "Hello,\n\n"
            . "{$yourself} {$action} the group buy for \"<strong>" . e($group->item?->name) . "</strong>\" from <strong>" . e($group->store?->name) . "</strong>.\n\n"
            . "Members: " . $group->committed_count . " of " . $group->member_limit . "\n"
            . "Your share: GH₵ " . number_format($newMember->item_share_ghs, 2) . " (" . $newMember->share_units . " units)\n"
            . ($consent ? $consent . "\n" : '')
            . ($isNew ? "No payment has been taken yet. You will be asked to pay when the group is full.\n" : '')
            . "\n-Aduanefie Team";
    }

    private function groupLockedBody(GroupBuy $group, array $recipient): string
    {
        $participant = $group->participants->first(function ($p) use ($recipient) {
            $user = $recipient['user'];
            return $user && (int) $p->user_id === (int) $user->id;
        });
        $share = $participant ? 'GH₵ ' . number_format($participant->item_share_ghs, 2) : 'TBD';

        return "Hello,\n\n"
            . "Your group buy for \"<strong>" . e($group->item?->name) . "</strong>\" from <strong>" . e($group->store?->name) . "</strong> is now full.\n\n"
            . "All " . $group->member_limit . " members have joined. Checkout your share before " . ($group->closes_at ? $group->closes_at->format('Y-m-d H:i') : '-') . ".\n"
            . "Your share: <strong>" . $share . "</strong>\n\n"
            . "No money moves until you checkout. Once everyone pays, the store ships to each member's address.\n\n"
            . "-Aduanefie Team";
    }

    private function paymentReminderBody(GroupBuy $group, GroupBuyParticipant $participant): string
    {
        return "Hello,\n\n"
            . "You still haven't checked out your share of the group buy for \"<strong>" . e($group->item?->name) . "</strong>\" from <strong>" . e($group->store?->name) . "</strong>.\n\n"
            . "Your share: GH₵ " . number_format($participant->item_share_ghs, 2) . " (" . $participant->share_units . " units)\n"
            . "Group closes: " . ($group->closes_at ? $group->closes_at->format('Y-m-d H:i') : '-') . "\n\n"
            . "Pay now to secure your share. If the group expires before you pay, you will not be charged.\n\n"
            . "-Aduanefie Team";
    }

    private function shippedBody(GroupBuyParticipant $participant): string
    {
        $link = $participant->gpsLink();
        $tracking = $link ? "<br><a href=\"" . $link . "\">Track delivery on Google Maps</a>" : '';

        return "Hello,\n\n"
            . "Your group buy order for \"<strong>" . e($participant->groupBuy->item?->name) . "</strong>\" has been shipped."
            . $tracking . "\n\n"
            . "-Aduanefie Team";
    }

    private function deliveredBody(GroupBuyParticipant $participant): string
    {
        return "Hello,\n\n"
            . "Your group buy order for \"<strong>" . e($participant->groupBuy->item?->name) . "</strong>\" has been delivered.\n\n"
            . "Thank you for buying together!\n\n"
            . "-Aduanefie Team";
    }

    private function groupReleasedBody(GroupBuy $group, string $label, array $recipient): string
    {
        return "Hello,\n\n"
            . "The group buy for \"<strong>" . e($group->item?->name) . "</strong>\" from <strong>" . e($group->store?->name) . "</strong> has been " . $label . ".\n\n"
            . "No payment was taken from any member. You are free to join or start another group.\n\n"
            . "-Aduanefie Team";
    }
}
