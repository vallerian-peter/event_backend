<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for sending transactional emails via Brevo (formerly Sendinblue) API.
 */
class BrevoEmailService
{
    protected string $apiKey;

    protected string $senderEmail;

    protected string $senderName;

    protected string $apiUrl = 'https://api.brevo.com/v3/smtp/email';

    public function __construct()
    {
        $this->apiKey = (string) config('services.brevo.api_key', '');
        $this->senderEmail = (string) config('services.brevo.sender_email', 'noreply@iventpro.com');
        $this->senderName = (string) config('services.brevo.sender_name', 'IventPro');
    }

    /**
     * Send a transactional email with HTML content.
     *
     * @return array<string, mixed>
     */
    public function sendTransactionalEmail(string $recipientEmail, string $recipientName, string $subject, string $htmlContent): array
    {
        if (app()->environment('testing') || empty($this->apiKey)) {
            Log::info("Brevo Email simulated for {$recipientEmail}: Subject: {$subject}");

            return [
                'successful' => true,
                'message' => 'Email simulated successfully (testing mode)',
            ];
        }

        try {
            $payload = [
                'sender' => [
                    'name' => $this->senderName,
                    'email' => $this->senderEmail,
                ],
                'to' => [
                    [
                        'email' => $recipientEmail,
                        'name' => $recipientName,
                    ],
                ],
                'subject' => $subject,
                'htmlContent' => $htmlContent,
            ];

            $response = Http::withHeaders([
                'api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout(10)->post($this->apiUrl, $payload);

            if ($response->successful()) {
                return [
                    'successful' => true,
                    'data' => $response->json(),
                ];
            }

            Log::error('Brevo Email Delivery Failed', [
                'status' => $response->status(),
                'response' => $response->body(),
                'recipient' => $recipientEmail,
            ]);

            return [
                'successful' => false,
                'error' => $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::error('Brevo Email Exception: '.$e->getMessage(), [
                'recipient' => $recipientEmail,
            ]);

            return [
                'successful' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
