<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\PaymentRequest;
use App\Traits\Processor;
use Unicodeveloper\Paystack\Facades\Paystack;

class PaystackController extends Controller
{
    use Processor;

    private PaymentRequest $payment;
    private $user;

    public function __construct(PaymentRequest $payment, User $user)
    {
        $config = $this->payment_config('paystack', 'payment_config');
        $values = false;
        if (!is_null($config) && $config->mode == 'live') {
            $values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $values = json_decode($config->test_values);
        }

        if ($values) {
            $config = array(
                'publicKey' => env('PAYSTACK_PUBLIC_KEY', $values->public_key),
                'secretKey' => env('PAYSTACK_SECRET_KEY', $values->secret_key),
                'paymentUrl' => env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co'),
                'merchantEmail' => env('MERCHANT_EMAIL', $values->merchant_email),
            );
            Config::set('paystack', $config);
        }

        $this->payment = $payment;
        $this->user = $user;
    }

    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        $payer = json_decode($data['payer_information']);

        $reference = Paystack::genTranxRef();

        return view('payment-views.paystack', compact('data', 'payer', 'reference'));
    }

    public function redirectToGateway(Request $request)
    {
        return Paystack::getAuthorizationUrl()->redirectNow();
    }

    public function handleGatewayCallback(Request $request)
    {
        $paymentDetails = $this->fetchPaymentData();
        $metadata = $paymentDetails['data']['metadata'] ?? [];
        $paymentId = $metadata['payment_id'] ?? $request->input('payment_id');
        $orderId = $metadata['orderID'] ?? $request->input('orderID');

        $lookupCol = !empty($paymentId) ? 'id' : 'attribute_id';
        $lookupVal = !empty($paymentId) ? $paymentId : $orderId;

        if (empty($lookupVal)) {
            return redirect()->route('payment-fail');
        }

        $gatewayStatus = ($paymentDetails['status'] ?? false) === true
            && (($paymentDetails['data']['status'] ?? 'success') === 'success');

        if ($gatewayStatus) {
            $reference = (string) ($paymentDetails['data']['reference'] ?? $request->input('trxref') ?? $request->input('reference') ?? '');
            $amountKobo = $paymentDetails['data']['amount'] ?? null;
            $verifiedAmount = is_numeric($amountKobo) ? ((float) $amountKobo / 100.0) : null;
            $verifiedCurrency = isset($paymentDetails['data']['currency']) ? (string) $paymentDetails['data']['currency'] : null;

            try {
                $data = $this->finalizePaymentSuccess(
                    $lookupCol,
                    $lookupVal,
                    'paystack',
                    $reference,
                    $verifiedAmount,
                    $verifiedCurrency
                );
                if ($data && (int) $data->is_paid === 1) {
                    return $this->payment_response($data, 'success');
                }
            } catch (\Throwable $e) {
                info('Paystack callback verification failed: ' . $e->getMessage());
                $existing = $this->payment::where($lookupCol, $lookupVal)->first();
                return $this->payment_response($existing, 'fail');
            }
        }

        $payment_data = $this->finalizePaymentFailure($lookupCol, $lookupVal);
        return $this->payment_response($payment_data, 'fail');
    }

    protected function fetchPaymentData(): array
    {
        return (array) Paystack::getPaymentData();
    }
}
