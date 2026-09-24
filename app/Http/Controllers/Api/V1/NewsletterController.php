<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inquiries\SubscribeNewsletterRequest;
use App\Services\InquiryService;
use Illuminate\Http\JsonResponse;

class NewsletterController extends Controller
{
    public function __construct(
        protected InquiryService $inquiryService
    ) {}

    /**
     * Subscribe email to newsletter updates.
     *
     * POST /api/v1/public/newsletter/subscribe
     * POST /api/v1/newsletter/subscribe
     */
    public function subscribe(SubscribeNewsletterRequest $request): JsonResponse
    {
        $result = $this->inquiryService->subscribeNewsletter(
            email: $request->validated('email'),
            source: $request->validated('source') ?? 'footer',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => [
                'email' => $result['subscriber']->email,
                'subscriptionStatus' => $result['status'],
                'subscribedAt' => $result['subscriber']->createdAt,
            ],
        ], 200);
    }
}
