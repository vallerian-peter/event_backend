<?php

namespace App\Services;

use App\Models\NewsletterSubscriber;
use App\Models\ProjectBrief;
use App\Services\Messaging\BeemSmsService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InquiryService
{
    public function __construct(
        protected ?BeemSmsService $smsService = null
    ) {}

    /**
     * Subscribe an email address to the newsletter.
     *
     * @return array{subscriber: NewsletterSubscriber, status: string, message: string}
     */
    public function subscribeNewsletter(
        string $email,
        ?string $source = 'footer',
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $normalizedEmail = strtolower(trim($email));

        $subscriber = NewsletterSubscriber::where('email', $normalizedEmail)->first();

        if ($subscriber) {
            if ($subscriber->status === 'active') {
                return [
                    'subscriber' => $subscriber,
                    'status' => 'already_subscribed',
                    'message' => 'You are already subscribed to iventPro updates.',
                ];
            }

            // Reactivate subscription
            $subscriber->update([
                'status' => 'active',
                'source' => $source ?: $subscriber->source,
                'ipAddress' => $ipAddress ?: $subscriber->ipAddress,
                'userAgent' => $userAgent ?: $subscriber->userAgent,
            ]);

            return [
                'subscriber' => $subscriber,
                'status' => 'reactivated',
                'message' => 'Welcome back! Your subscription has been reactivated.',
            ];
        }

        $subscriber = NewsletterSubscriber::create([
            'email' => $normalizedEmail,
            'source' => $source ?: 'footer',
            'status' => 'active',
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
            'verifiedAt' => Carbon::now(),
        ]);

        return [
            'subscriber' => $subscriber,
            'status' => 'subscribed',
            'message' => 'Thank you for subscribing to iventPro! Stay tuned for updates.',
        ];
    }

    /**
     * Create a project brief / event consultation order inquiry.
     *
     * @param  array<string, mixed>  $data
     */
    public function createProjectBrief(array $data): ProjectBrief
    {
        // Generate unique reference code e.g. BRF-9X4K2M
        do {
            $refCode = 'BRF-'.strtoupper(Str::random(6));
        } while (ProjectBrief::where('referenceCode', $refCode)->exists());

        /** @var ProjectBrief $brief */
        $brief = ProjectBrief::create([
            'referenceCode' => $refCode,
            'fullName' => $data['fullName'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'eventType' => $data['eventType'],
            'eventDate' => $data['eventDate'] ?? null,
            'eventTime' => $data['eventTime'] ?? null,
            'venueLocation' => $data['venueLocation'] ?? null,
            'estimatedGuests' => $data['estimatedGuests'] ?? null,
            'budgetRange' => $data['budgetRange'] ?? null,
            'requestedServices' => $data['requestedServices'] ?? null,
            'message' => $data['message'],
            'status' => 'new',
        ]);

        // Attempt automated acknowledgment SMS if phone is provided
        $this->sendBriefAcknowledgementSms($brief);

        return $brief;
    }

    /**
     * Send instant SMS acknowledgement to client.
     */
    protected function sendBriefAcknowledgementSms(ProjectBrief $brief): void
    {
        try {
            if ($this->smsService && ! empty($brief->phone)) {
                $cleaned = preg_replace('/\D/', '', $brief->phone);
                if (str_starts_with($cleaned, '0')) {
                    $cleaned = '255'.substr($cleaned, 1);
                } elseif (! str_starts_with($cleaned, '255') && strlen($cleaned) === 9) {
                    $cleaned = '255'.$cleaned;
                }

                if (str_starts_with($cleaned, '255') && strlen($cleaned) === 12) {
                    $text = "Habari {$brief->fullName}, asante kwa kuwasiliana na iventPro. Tumepokea ombi lako la {$brief->eventType} (Kumb: {$brief->referenceCode}). Timu yetu itawasiliana nawe hivi punde.";
                    $this->smsService->sendSms($cleaned, $text);
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Could not dispatch acknowledgment SMS for project brief {$brief->referenceCode}: ".$e->getMessage());
        }
    }

    /**
     * List project briefs with search and filters.
     *
     * @return LengthAwarePaginator<ProjectBrief>
     */
    public function listProjectBriefs(
        ?string $status = null,
        ?string $search = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = ProjectBrief::query()->with('assignedTo')->latest('createdAt');

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('fullName', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('referenceCode', 'like', "%{$search}%")
                    ->orWhere('eventType', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Update status and notes for a project brief.
     */
    public function updateProjectBriefStatus(
        int $id,
        string $status,
        ?string $adminNotes = null,
        ?int $assignedToUserId = null
    ): ProjectBrief {
        $brief = ProjectBrief::findOrFail($id);

        $updates = ['status' => $status];

        if ($adminNotes !== null) {
            $updates['adminNotes'] = $adminNotes;
        }

        if ($assignedToUserId !== null) {
            $updates['assignedToUserId'] = $assignedToUserId;
        }

        if ($status === 'contacted' && empty($brief->contactedAt)) {
            $updates['contactedAt'] = Carbon::now();
        }

        $brief->update($updates);

        return $brief->fresh('assignedTo');
    }
}
