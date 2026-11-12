<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TradeContract;
use App\Models\CustomsDuty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\CentralLogics\Helpers;

class IntegrationController extends Controller
{
    public function freightWebhook(Request $request)
    {
        $request->validate([
            'contract_id' => 'required|exists:trade_contracts,id',
            'status' => 'required|in:booked,in_transit,delivered,exception',
            'tracking_number' => 'nullable|string|max:100',
            'estimated_delivery' => 'nullable|date',
            'provider' => 'required|string|max:50',
        ]);

        \Log::info('Freight webhook received', [
            'contract_id' => $request->contract_id,
            'status' => $request->status,
            'tracking_number' => $request->tracking_number,
            'provider' => $request->provider,
        ]);

        return response()->json(['success' => true, 'message' => 'Freight status updated.'], 200);
    }

    public function customsSubmit(Request $request)
    {
        $request->validate([
            'contract_id' => 'required|exists:trade_contracts,id',
            'destination_country' => 'required|string|max:100',
            'hs_code' => 'required|string|max:20',
            'declared_value' => 'required|numeric|min:0',
            'documents' => 'required|array',
            'documents.*' => 'string|max:255',
        ]);

        $dutyEstimate = CustomsDuty::calculateDuties(
            $request->declared_value,
            $request->destination_country,
            $request->hs_code
        );

        \Log::info('Customs submission', [
            'contract_id' => $request->contract_id,
            'destination_country' => $request->destination_country,
            'duty_estimate' => $dutyEstimate,
        ]);

        return response()->json([
            'success' => true,
            'duty_estimate' => $dutyEstimate,
            'message' => 'Customs documentation submitted.',
        ], 200);
    }

    public function bankPayout(Request $request)
    {
        $request->validate([
            'contract_id' => 'required|exists:trade_contracts,id',
            'bank_account' => 'required|string|max:100',
            'bank_name' => 'required|string|max:100',
            'country_code' => 'required|string|size:2',
            'amount' => 'required|numeric|min:0',
            'currency_code' => 'required|string|size:3',
        ]);

        $contract = TradeContract::find($request->contract_id);

        if ($contract->payment_status !== 'deposited') {
            return response()->json(['error' => 'Funds not in escrow.'], 422);
        }

        \Log::info('Bank payout initiated', [
            'contract_id' => $request->contract_id,
            'amount' => $request->amount,
            'currency' => $request->currency_code,
            'bank' => $request->bank_name,
        ]);

        return response()->json([
            'success' => true,
            'payout_reference' => 'PAY-' . strtoupper(uniqid()),
            'message' => 'Payout initiated to bank account.',
        ], 200);
    }

    public function paymentWebhook(Request $request, $gateway)
    {
        \Log::info("Payment webhook from $gateway", $request->all());

        return response()->json(['success' => true], 200);
    }

    public function supportedGateways()
    {
        return response()->json([
            'gateways' => [
                'flutterwave' => ['countries' => ['NG', 'GH', 'KE', 'UG', 'TZ', 'ZA'], 'currencies' => ['NGN', 'GHS', 'KES', 'UGX', 'TZS', 'ZAR']],
                'paystack' => ['countries' => ['NG', 'GH'], 'currencies' => ['NGN', 'GHS']],
                'mobile_money' => ['countries' => ['GH', 'KE', 'UG', 'TZ'], 'currencies' => ['GHS', 'KES', 'UGX', 'TZS']],
                'bank_transfer' => ['countries' => ['ALL'], 'currencies' => ['ALL']],
            ],
        ], 200);
    }
}
