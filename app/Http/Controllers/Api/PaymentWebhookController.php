<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payment\Exceptions\InvalidSignatureException;
use App\Services\Payment\Exceptions\PaymentProviderException;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function __invoke(string $provider, Request $request): JsonResponse
    {
        try {
            $payment = $this->paymentService->handleWebhook($provider, $request);

            return response()->json([
                'success' => true,
                'payment_id' => $payment->id,
                'status' => $payment->status,
            ]);
        } catch (InvalidSignatureException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 401);
        } catch (PaymentProviderException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 400);
        }
    }
}
