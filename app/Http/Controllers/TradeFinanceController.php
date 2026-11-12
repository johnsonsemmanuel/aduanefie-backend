<?php

namespace App\Http\Controllers;

use App\Models\TradeContract;
use App\Models\TradeRequest;
use App\Models\WalletTransaction;
use App\Models\StoreWallet;
use App\CentralLogics\Helpers;
use App\Exports\TradeContractExport;
use App\Exports\TradeContractInvoiceExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class TradeFinanceController extends Controller
{
    /**
     * Create a trade contract from an accepted quote.
     *
     * @return \Illuminate\Http\Response
     */
    public function createContract(Request $request, $tradeRequestId)
    {
        $tradeRequest = TradeRequest::findOrFail($tradeRequestId);

        $userId = auth('customer')->id() ?? auth('vendor')->id();
        if ($tradeRequest->buyer_id != $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($tradeRequest->status !== 'accepted') {
            return response()->json(['error' => 'Quote must be accepted first.'], 422);
        }

        $existing = $tradeRequest->contract;
        if ($existing) {
            return response()->json(['success' => true, 'contract' => $existing]);
        }

        $contract = $tradeRequest->createContractFromQuote();

        Toastr::success('Trade contract created.');

        return response()->json([
            'success' => true,
            'contract' => $contract,
        ]);
    }

    /**
     * Deposit funds into escrow for a trade contract.
     *
     * @return \Illuminate\Http\Response
     */
    public function deposit(Request $request, $contractId)
    {
        $contract = TradeContract::findOrFail($contractId);

        // Verify buyer owns this contract
        $userId = auth('customer')->id() ?? auth('vendor')->id();
        if ($contract->buyer_id != $userId) {
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

        // Deduct from buyer wallet
        $userModel = auth('customer')->check() ? auth('customer')->user() : auth('vendor')->user();
        $buyerWalletBalance = (float) ($userModel->wallet_balance ?? 0);
        if ($buyerWalletBalance < $depositAmount) {
            return response()->json(['error' => 'Insufficient wallet balance.'], 422);
        }

        $transactionId = (string) Str::uuid();

        // Debit buyer
        WalletTransaction::create([
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'debit' => $depositAmount,
            'credit' => 0,
            'balance' => $buyerWalletBalance - $depositAmount,
            'transaction_type' => 'escrow_deposit',
            'reference' => 'TC-' . $contract->id,
        ]);

        // Update user wallet balance
        $userModel->update(['wallet_balance' => $buyerWalletBalance - $depositAmount]);

        // Update contract
        $contract->update([
            'escrow_amount' => $depositAmount,
            'payment_status' => 'deposited',
        ]);

        Toastr::success('Funds deposited into escrow.');

        return response()->json([
            'success' => true,
            'contract' => $contract,
            'transaction_id' => $transactionId,
        ]);
    }

    /**
     * Confirm delivery of goods by buyer.
     *
     * @return \Illuminate\Http\Response
     */
    public function confirmDelivery(Request $request, $contractId)
    {
        $contract = TradeContract::findOrFail($contractId);

        $userId = auth('customer')->id() ?? auth('vendor')->id();
        if ($contract->buyer_id != $userId) {
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

        Toastr::success('Delivery confirmation recorded.');

        return response()->json([
            'success' => true,
            'contract' => $contract->fresh(),
        ]);
    }

    /**
     * Verify trade documents by buyer.
     *
     * @return \Illuminate\Http\Response
     */
    public function verifyDocuments(Request $request, $contractId)
    {
        $contract = TradeContract::findOrFail($contractId);

        $userId = auth('customer')->id() ?? auth('vendor')->id();
        if ($contract->buyer_id != $userId) {
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

        Toastr::success('Document verification recorded.');

        return response()->json([
            'success' => true,
            'contract' => $contract->fresh(),
            'can_release' => $contract->canReleaseFunds(),
        ]);
    }

    /**
     * Release escrow funds to seller.
     *
     * @return \Illuminate\Http\Response
     */
    public function releaseFunds(Request $request, $contractId)
    {
        $contract = TradeContract::findOrFail($contractId);

        $userId = auth('customer')->id() ?? auth('vendor')->id();
        if ($contract->buyer_id != $userId) {
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

        // Credit seller wallet
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

        Toastr::success('Funds released to seller.');

        return response()->json([
            'success' => true,
            'contract' => $contract->fresh(),
            'amount_released' => $escrowAmount,
        ]);
    }

    /**
     * Refund escrow funds to buyer.
     *
     * @return \Illuminate\Http\Response
     */
    public function refund(Request $request, $contractId)
    {
        $contract = TradeContract::findOrFail($contractId);

        $userId = auth('customer')->id() ?? auth('vendor')->id();
        if ($contract->buyer_id != $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($contract->payment_status !== 'deposited') {
            return response()->json(['error' => 'Only deposited funds can be refunded.'], 422);
        }

        $refundAmount = (float) $contract->escrow_amount;
        $userModel = auth('customer')->check() ? auth('customer')->user() : auth('vendor')->user();
        $currentBalance = (float) ($userModel->wallet_balance ?? 0);

        WalletTransaction::create([
            'user_id' => $userId,
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

        Toastr::success('Funds refunded to buyer.');

        return response()->json([
            'success' => true,
            'contract' => $contract->fresh(),
        ]);
    }

    /**
     * Get trade finance summary for a contract.
     *
     * @return \Illuminate\Http\Response
     */
    public function summary($contractId)
    {
        $contract = TradeContract::with(['tradeRequest', 'tradeQuote', 'assurance'])->findOrFail($contractId);

        $userId = auth('customer')->id() ?? auth('vendor')->id();
        if ($contract->buyer_id != $userId && $contract->seller_id != $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'contract' => $contract,
            'can_release' => $contract->canReleaseFunds(),
            'status_label' => $contract->getStatusLabel(),
        ]);
    }

    /**
     * Show trade contract page (buyer/seller view).
     *
     * @return \Illuminate\Http\Response
     */
    public function view(Request $request, $contractId)
    {
        $contract = TradeContract::with(['tradeRequest.product', 'tradeQuote', 'assurance'])->findOrFail($contractId);

        $userId = auth('customer')->id() ?? auth('vendor')->id();
        if ($contract->buyer_id != $userId && $contract->seller_id != $userId) {
            return back()->with('error', 'Unauthorized');
        }

        $canRelease = $contract->canReleaseFunds();
        $canDeposit = $contract->payment_status === 'pending';
        $canConfirmDelivery = $contract->payment_status === 'deposited' && $contract->delivery_status === 'pending';
        $canVerifyDocs = $contract->payment_status === 'deposited' && $contract->delivery_status === 'delivered';
        $canRefund = $contract->payment_status === 'deposited';

        return view('trade-contract.view', compact(
            'contract', 'canRelease', 'canDeposit', 'canConfirmDelivery', 'canVerifyDocs', 'canRefund'
        ));
    }

    /**
     * Export trade contracts to Excel/CSV.
     *
     * @return \Illuminate\Http\Response
     */
    public function exportContracts(Request $request, $type)
    {
        $userId = auth('customer')->id() ?? auth('vendor')->id();
        $userType = auth('customer')->check() ? 'customer' : 'vendor';

        $query = TradeContract::with(['tradeRequest.product', 'tradeQuote']);

        if ($userType === 'customer') {
            $query->where('buyer_id', $userId);
        } else {
            $query->where('seller_id', $userId);
        }

        $tradeContracts = $query->latest()->get();

        $fileName = 'trade_contracts_' . date('Ymd_His') . '.' . $type;

        return Excel::download(new TradeContractExport($tradeContracts), $fileName);
    }

    /**
     * Export a single trade contract invoice to Excel/CSV.
     *
     * @return \Illuminate\Http\Response
     */
    public function exportInvoice(Request $request, $contractId, $type)
    {
        $contract = TradeContract::with(['tradeRequest.product', 'tradeQuote'])->findOrFail($contractId);

        $userId = auth('customer')->id() ?? auth('vendor')->id();
        if ($contract->buyer_id != $userId && $contract->seller_id != $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $fileName = 'trade_invoice_' . $contractId . '_' . date('Ymd_His') . '.' . $type;

        return Excel::download(new TradeContractInvoiceExport($contract), $fileName);
    }
}
