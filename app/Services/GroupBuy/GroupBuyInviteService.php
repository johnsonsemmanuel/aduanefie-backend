<?php

namespace App\Services\GroupBuy;

use App\Models\GroupBuy;
use App\Models\GroupBuyInvite;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Sender-initiated refer-a-friend invites for a group buy.
 *
 * Mail is only sent when a signed-in member explicitly submits a friend's
 * address. No inferred-interest bulk email is sent from purchase or wishlist
 * data. A failed send is reported to the caller and leaves no invite row.
 */
final class GroupBuyInviteService
{
    public function __construct(private GroupBuyService $groups)
    {
    }

    public function send(GroupBuy $group, User $sender, string $email, ?string $message = null): GroupBuyInvite
    {
        $email = strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Enter a valid email address.', 403);
        }

        $since = now()->subDay();
        $already = GroupBuyInvite::where('group_buy_id', $group->id)
            ->where('email', $email)
            ->where('created_at', '>=', $since)
            ->exists();
        if ($already) {
            throw new \RuntimeException('An invite was already sent to this address in the last 24 hours.', 429);
        }

        $sentToday = GroupBuyInvite::where('sender_user_id', $sender->id)
            ->where('created_at', '>=', $since)
            ->count();
        if ($sentToday >= 20) {
            throw new \RuntimeException('You have sent the daily limit of group invites. Try again tomorrow.', 429);
        }

        $cleanMessage = $this->clean($message, 300);
        $senderName = $this->clean($sender->full_name, 80) ?: 'A fellow shopper';
        $link = $this->groups->shareLink($group)['web'];
        $shareValue = $this->groups->resolvedUnitPrice($group) * (float) $group->share_units;
        $share = 'GH₵ ' . number_format($shareValue, 2);
        $item = $this->clean($group->item?->name, 120) ?: 'a group buy';
        $store = $this->clean($group->store?->name, 120) ?: 'a supplier';
        $spots = max(0, (int) $group->member_limit - (int) $group->committed_count);

        $body = "Hello,\n\n"
            . "{$senderName} invited you to join a shared group buy on Aduanefie.\n\n"
            . "Product: {$item}\n"
            . "Supplier: {$store}\n"
            . "Your share: {$share}\n"
            . "Spots left: {$spots}\n\n"
            . ($cleanMessage ? "Message from {$senderName}: {$cleanMessage}\n\n" : '')
            . "Open the group: {$link}\n\n"
            . "Nobody is charged until the group is complete. This invite was sent because "
            . "{$senderName} entered your email address. If you did not expect it, you can ignore this message.\n";

        $invite = GroupBuyInvite::create([
            'group_buy_id' => $group->id,
            'sender_user_id' => $sender->id,
            'email' => $email,
            'message' => $cleanMessage,
        ]);

        try {
            Mail::raw($body, function ($mail) use ($email, $item) {
                $mail->to($email)->subject('Join this Aduanefie group buy: ' . $item);
            });
        } catch (\Throwable $e) {
            $invite->delete();
            throw new \RuntimeException('The invite could not be sent. Please try again.', 502);
        }

        return $invite;
    }

    private function clean(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = strip_tags($value);
        $value = preg_replace('/\s+/', ' ', $value) ?: '';
        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }
}
