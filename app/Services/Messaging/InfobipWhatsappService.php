<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for sending WhatsApp notifications and template messages via InfoBip API.
 */
class InfobipWhatsappService
{
    protected string $baseUrl;

    protected string $apiKey;

    protected string $whatsappSender;

    protected bool $enabled;

    public function __construct()
    {
        $this->baseUrl = (string) config('services.infobip.base_url', 'api.infobip.com');
        $this->apiKey = (string) config('services.infobip.api_key', '');
        $this->whatsappSender = (string) config('services.infobip.whatsapp_sender', '');
        $this->enabled = (bool) config('services.infobip.enabled', true);
    }

    /**
     * Send a registered WhatsApp template message with dynamic placeholders.
     *
     * @param  array<string>  $placeholders
     * @return array<string, mixed>
     */
    public function sendTemplateMessage(string $phone, string $templateName, array $placeholders = [], string $language = 'en_US'): array
    {
        $normalizedPhone = (new BeemSmsService)->normalizePhone($phone);

        if (! $this->enabled || app()->environment('testing')) {
            Log::info("InfoBip WhatsApp simulated for {$normalizedPhone} using template [{$templateName}]:", $placeholders);

            return [
                'successful' => true,
                'message' => 'WhatsApp template message simulated successfully (testing mode)',
                'recipient' => $normalizedPhone,
            ];
        }

        try {
            $endpoint = "https://{$this->baseUrl}/whatsapp/1/message/template";

            $payload = [
                'messages' => [
                    [
                        'from' => $this->whatsappSender,
                        'to' => $normalizedPhone,
                        'content' => [
                            'templateName' => $templateName,
                            'templateData' => [
                                'body' => [
                                    'placeholders' => $placeholders,
                                ],
                            ],
                            'language' => $language,
                        ],
                    ],
                ],
            ];

            $response = Http::withHeaders([
                'Authorization' => "App {$this->apiKey}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout(10)->post($endpoint, $payload);

            if ($response->successful()) {
                return [
                    'successful' => true,
                    'data' => $response->json(),
                ];
            }

            Log::error('InfoBip WhatsApp Delivery Failed', [
                'status' => $response->status(),
                'response' => $response->body(),
                'recipient' => $normalizedPhone,
            ]);

            return [
                'successful' => false,
                'error' => $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::error('InfoBip WhatsApp Exception: '.$e->getMessage(), [
                'recipient' => $normalizedPhone,
            ]);

            return [
                'successful' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send standard outbound WhatsApp text message.
     *
     * @return array<string, mixed>
     */
    public function sendTextMessage(string $phone, string $text): array
    {
        $normalizedPhone = (new BeemSmsService)->normalizePhone($phone);

        if (! $this->enabled || app()->environment('testing')) {
            Log::info("InfoBip WhatsApp text simulated for {$normalizedPhone}: {$text}");

            return [
                'successful' => true,
                'message' => 'WhatsApp text simulated successfully (testing mode)',
                'recipient' => $normalizedPhone,
            ];
        }

        try {
            $endpoint = "https://{$this->baseUrl}/whatsapp/1/message/text";

            $payload = [
                'from' => $this->whatsappSender,
                'to' => $normalizedPhone,
                'content' => [
                    'text' => $text,
                ],
            ];

            $response = Http::withHeaders([
                'Authorization' => "App {$this->apiKey}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout(10)->post($endpoint, $payload);

            return [
                'successful' => $response->successful(),
                'data' => $response->json(),
            ];
        } catch (\Throwable $e) {
            return [
                'successful' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
