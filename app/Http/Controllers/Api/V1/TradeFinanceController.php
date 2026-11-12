<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TradeContract;
use App\Models\TradeRequest;
use App\Models\TradeRequestQuote;
use App\Models\WalletTransaction;
use App\Models\StoreWallet;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TradeFinanceController extends Controller
{
    public function createContract(Request $request, $tradeRequestId)
    {
        $tradeRequest = TradeRequest::findOrFail($tradeRequestId);

        $user = $request->user();
        if (!$user || $tradeRequest->buyer_id != $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($tradeRequest->status !== 'accepted') {
            return response()->json(['error' => 'Quote must be accepted first.'], 422);
        }

        $existing = $tradeRequest->contract;
        if ($existing) {
            return response()->json(['success' => true, 'contract' => $existing], 200);
        }

        $contract = $tradeRequest->createContractFromQuote();

        return response()->json(['success' => true, 'contract' => $contract], 200);
    }

    public function deposit(Request $request, $contractId)
    {
        $contract = TradeContract::findOrFail($contractId);

        $user = $request->user();
        if (!$user || $contract->buyer_id != $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($contract->payment_status !== 'pending') {
            return response()->json(['error' => 'Contract already has payment status: ' . $contract->payment_status], 422);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $depositAmount = (float) $request->amount;
        $contractAmount = (float) $contract->contract_amount;

        if ($depositAmount < $contractAmount) {
            return response()->json(['error' => 'Deposit must be at least the contract amount.'], 422);
        }

        $userModel = $user;
        $buyerWalletBalance = (float) ($userModel->wallet_balance ?? 0);
        if ($buyerWalletBalance < $depositAmount) {
            return response()->json(['error' => 'Insufficient wallet balance.'], 422);
        }

        $transactionId = (string) Str::uuid();

        WalletTransaction::create([
            'user_id' => $user->id,
            'transaction_id' => $transactionId,
            'debit' => $depositAmount,
            'credit' => 0,
            'balance' => $buyerWalletBalance - $depositAmount,
            'transaction_type' => 'escrow_deposit',
            'reference' => 'TC-' . $contract->id,
        ]);

        $userModel->update(['wallet_balance' => $buyerWalletBalance - $depositAmount]);

        $contract->update([
            'escrow_amount' => $depositAmount,
            'payment_status' => 'deposited',
        ]);

        return response()->json([
            'success' => true,
            'contract' => $contract,
            'transaction_id' => $transactionId,
        ], 200);
    }

    public function confirmDelivery(Request $request, $contractId)
    {
        $contract = TradeContract::findOrFail($contractId);

        $user = $request->user();
        if (!$user || $contract->buyer_id != $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($contract->payment_status !== 'deposited') {
            return response()->json(['error' => 'Funds must be deposited first.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'delivery_confirmed' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $contract->update([
            'delivery_status' => $request->delivery_confirmed ? 'delivered' : 'pending',
            'delivery_confirmed_at' => $request->delivery_confirmed ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'contract' => $contract->fresh(),
        ], 200);
    }

    public function verifyDocuments(Request $request, $contractId)
    {
        $contract = TradeContract::findOrFail($contractId);

        $user = $request->user();
        if (!$user || $contract->buyer_id != $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'docs_verified' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $contract->update([
            'delivery_status' => $request->docs_verified ? 'docs_verified' : $contract->delivery_status,
            'docs_verified_at' => $request->docs_verified ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'contract' => $contract->fresh(),
            'can_release' => $contract->canReleaseFunds(),
        ], 200);
    }

    public function releaseFunds(Request $request, $contractId)
    {
        $contract = TradeContract::findOrFail($contractId);

        $user = $request->user();
        if (!$user || $contract->buyer_id != $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$contract->canReleaseFunds()) {
            return response()->json(['error' => 'Funds cannot be released. Delivery and document verification required.'], 422);
        }

        $escrowAmount = (float) $contract->escrow_amount;
        $sellerWallet = StoreWallet::where('vendor_id', $contract->seller_id)->first();

        if (!$sellerWallet) {
            return response()->json(['error' => 'Seller wallet not found.'], 422);
        }

        $sellerWallet->increment('total_earning', $escrowAmount);

        WalletTransaction::create([
            'user_id' => $contract->seller_id,
            'transaction_id' => (string) Str::uuid(),
            'credit' => $escrowAmount,
            'debit' => 0,
            'balance' => $sellerWallet->getBalanceAttribute(),
            'transaction_type' => 'escrow_release',
            'reference' => 'TC-' . $contract->id,
        ]);

        $contract->update([
            'payment_status' => 'released',
            'delivery_status' => 'completed',
            'funds_released_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'contract' => $contract->fresh(),
            'amount_released' => $escrowAmount,
        ], 200);
    }

    public function refund(Request $request, $contractId)
    {
        $contract = TradeContract::findOrFail($contractId);

        $user = $request->user();
        if (!$user || $contract->buyer_id != $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($contract->payment_status !== 'deposited') {
            return response()->json(['error' => 'Only deposited funds can be refunded.'], 422);
        }

        $refundAmount = (float) $contract->escrow_amount;
        $userModel = $user;
        $currentBalance = (float) ($userModel->wallet_balance ?? 0);

        WalletTransaction::create([
            'user_id' => $user->id,
            'transaction_id' => (string) Str::uuid(),
            'credit' => $refundAmount,
            'debit' => 0,
            'balance' => $currentBalance + $refundAmount,
            'transaction_type' => 'escrow_refund',
            'reference' => 'TC-' . $contract->id,
        ]);

        $userModel->update(['wallet_balance' => $currentBalance + $refundAmount]);

        $contract->update([
            'payment_status' => 'refunded',
            'escrow_amount' => 0,
        ]);

        return response()->json([
            'success' => true,
            'contract' => $contract->fresh(),
        ], 200);
    }

    public function summary($contractId)
    {
        $contract = TradeContract::with(['tradeRequest', 'tradeQuote', 'assurance'])->findOrFail($contractId);

        $user = $request->user();
        if (!$user || ($contract->buyer_id != $user->id && $contract->seller_id != $user->id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'contract' => $contract,
            'can_release' => $contract->canReleaseFunds(),
            'status_label' => $contract->getStatusLabel(),
        ], 200);
    }

    public function view(Request $request, $contractId)
    {
        return $this->summary($request, $contractId);
    }
}
