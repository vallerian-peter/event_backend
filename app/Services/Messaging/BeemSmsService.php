<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for sending SMS messages and OTP codes via Beem Africa API.
 */
class BeemSmsService
{
    protected string $apiKey;

    protected string $secretKey;

    protected string $senderName;

    protected bool $enabled;

    protected string $apiUrl = 'https://apisms.beem.africa/v1/send';

    public function __construct()
    {
        $this->apiKey = (string) config('services.beem.api_key', '');
        $this->secretKey = (string) config('services.beem.secret_key', '');
        $this->senderName = (string) config('services.beem.sender_name', 'IventPro');
        $this->enabled = (bool) config('services.beem.enabled', true);
    }

    /**
     * Send a 6-digit OTP code to the specified phone number.
     *
     * @return array<string, mixed>
     */
    public function sendOtp(string $phone, string $otp): array
    {
        $message = "Your Ivento Pro verification code is: {$otp}. It is valid for 5 minutes. Do not share this code with anyone.";

        return $this->sendSms($phone, $message);
    }

    /**
     * Send an arbitrary SMS message via Beem Africa.
     *
     * @return array<string, mixed>
     */
    public function sendSms(string $phone, string $message): array
    {
        $formattedPhone = $this->normalizePhone($phone);

        // In test mode or when disabled, avoid external HTTP calls
        if (! $this->enabled || app()->environment('testing')) {
            Log::info("Beem SMS simulated for {$formattedPhone}: {$message}");

            return [
                'isSuccessful' => true,
                'code' => 100,
                'message' => 'SMS queued successfully (testing mode)',
                'recipient' => $formattedPhone,
            ];
        }

        try {
            $payload = [
                'source_addr' => $this->senderName,
                'schedule_time' => '',
                'encoding' => 0,
                'message' => $message,
                'recipients' => [
                    [
                        'recipient_id' => 1,
                        'dest_addr' => $formattedPhone,
                    ],
                ],
            ];

            $response = Http::withBasicAuth($this->apiKey, $this->secretKey)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->timeout(10)
                ->post($this->apiUrl, $payload);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'isSuccessful' => true,
                    'requestId' => $data['request_id'] ?? null,
                    'code' => $data['code'] ?? 100,
                    'message' => $data['message'] ?? 'Message Submitted Successfully',
                    'validCount' => $data['valid'] ?? 1,
                    'invalidCount' => $data['invalid'] ?? 0,
                    'duplicateCount' => $data['duplicates'] ?? 0,
                ];
            }

            Log::error('Beem SMS Delivery Failed', [
                'status' => $response->status(),
                'response' => $response->body(),
                'recipient' => $formattedPhone,
            ]);

            return [
                'isSuccessful' => false,
                'error' => $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::error('Beem SMS Exception: '.$e->getMessage(), [
                'phone' => $formattedPhone,
            ]);

            return [
                'isSuccessful' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Normalize East African / international phone numbers to standard format (e.g. 2557XXXXXXXX).
     */
    public function normalizePhone(string $phone): string
    {
        // Strip all non-numeric characters except leading +
        $cleaned = preg_replace('/[^\d]/', '', $phone);

        // Convert local format starting with 0 to 255 prefix
        if (str_starts_with($cleaned, '0')) {
            return '255'.substr($cleaned, 1);
        }

        // If starts with + or already 255, ensure 255
        if (str_starts_with($cleaned, '255')) {
            return $cleaned;
        }

        return $cleaned;
    }

    /**
     * Mask a phone number for user privacy (e.g. +255 712 *** 567).
     */
    public static function maskPhone(string $phone): string
    {
        $cleaned = preg_replace('/[^\d]/', '', $phone);
        $len = strlen($cleaned);

        if ($len < 7) {
            return $phone;
        }

        $start = substr($cleaned, 0, 5);
        $end = substr($cleaned, -3);

        return "+{$start} *** {$end}";
    }
}
