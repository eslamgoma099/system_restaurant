<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymobService
{
    protected $apiKey;
    protected $integrationId;
    protected $iframeId;
    protected $hmacSecret;
    protected $baseUrl = 'https://accept.paymob.com/api';

    public function __construct()
    {
        $this->apiKey = config('services.paymob.api_key');
        $this->integrationId = config('services.paymob.integration_id');
        $this->iframeId = config('services.paymob.iframe_id');
        $this->hmacSecret = config('services.paymob.hmac_secret');
    }

    public function getAuthToken()
    {
        try {
            $response = Http::post($this->baseUrl . '/auth/tokens', [
                'api_key' => $this->apiKey
            ]);

            if ($response->successful()) {
                return $response->json()['token'];
            }

            Log::error('Paymob auth token error', ['response' => $response->json()]);
            return null;
        } catch (\Exception $e) {
            Log::error('Paymob auth token exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function createOrder($amount, $orderId)
    {
        $token = $this->getAuthToken();
        if (!$token) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token
            ])->post($this->baseUrl . '/ecommerce/orders', [
                'auth_token' => $token,
                'delivery_needed' => false,
                'amount_cents' => $amount * 100, // Convert to cents
                'currency' => 'EGP',
                'merchant_order_id' => $orderId,
                'items' => []
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Paymob create order error', ['response' => $response->json()]);
            return null;
        } catch (\Exception $e) {
            Log::error('Paymob create order exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function getPaymentKey($orderId, $amount, $billingData)
    {
        $token = $this->getAuthToken();
        if (!$token) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token
            ])->post($this->baseUrl . '/acceptance/payment_keys', [
                'auth_token' => $token,
                'amount_cents' => $amount * 100,
                'expiration' => 3600,
                'order_id' => $orderId,
                'billing_data' => $billingData,
                'currency' => 'EGP',
                'integration_id' => $this->integrationId
            ]);

            if ($response->successful()) {
                return $response->json()['token'];
            }

            Log::error('Paymob payment key error', ['response' => $response->json()]);
            return null;
        } catch (\Exception $e) {
            Log::error('Paymob payment key exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function verifyPayment($transactionId)
    {
        try {
            $response = Http::get($this->baseUrl . '/acceptance/transactions/' . $transactionId);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Paymob verify payment error', ['response' => $response->json()]);
            return null;
        } catch (\Exception $e) {
            Log::error('Paymob verify payment exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function getIframeId()
    {
        return $this->iframeId;
    }
}