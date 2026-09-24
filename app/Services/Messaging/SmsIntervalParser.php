<?php

namespace App\Services\Messaging;

use Carbon\Carbon;

class SmsIntervalParser
{
    /**
     * Parse raw interval string (e.g. '5days', '1weeks', '12hours', '1months') into value and unit.
     *
     * @return array{value: int, unit: string}
     */
    public static function parse(?string $raw): array
    {
        $str = strtolower(trim($raw ?? '5days'));

        if (preg_match('/^(\d+)\s*(minute|minutes|hour|hours|day|days|week|weeks|month|months)$/', $str, $matches)) {
            $value = max(1, (int) $matches[1]);
            $unit = rtrim($matches[2], 's'); // normalize to singular: minute, hour, day, week, month

            return ['value' => $value, 'unit' => $unit];
        }

        return ['value' => 5, 'unit' => 'day'];
    }

    /**
     * Determine if a contribution reminder is due based on elapsed time since the last reminder.
     *
     * @param  string|null  $lastSentAt  ISO date string of last reminder sent
     * @param  string|null  $eventStart  Event start date (Y-m-d)
     * @param  string|null  $eventEnd    Event end date (Y-m-d)
     * @param  string|null  $intervalRaw Raw interval string (e.g. '5days')
     */
    public static function isContributionReminderDue(
        ?string $lastSentAt,
        ?string $eventStart,
        ?string $eventEnd,
        ?string $intervalRaw = '5days',
        ?Carbon $now = null
    ): bool {
        $now = $now ?? Carbon::now();

        // Reminders must stop 2 days before event end date (leaving the day before for near-event pass invitation)
        if ($eventEnd) {
            $end = Carbon::parse($eventEnd)->startOfDay();
            $cutoff = $end->copy()->subDays(1); // 1 day before end date is reserved for gate code invitation
            if ($now->greaterThanOrEqualTo($cutoff)) {
                return false;
            }
        }

        // Do not send if already sent today
        if ($lastSentAt) {
            $last = Carbon::parse($lastSentAt);
            if ($last->isSameDay($now)) {
                return false;
            }
        }

        $parsed = self::parse($intervalRaw);
        $value = $parsed['value'];
        $unit = $parsed['unit'];

        // If never sent before, measure elapsed time from event start date
        $reference = $lastSentAt ? Carbon::parse($lastSentAt) : ($eventStart ? Carbon::parse($eventStart) : $now->copy()->subDays($value));

        if ($reference->greaterThan($now)) {
            return false;
        }

        return match ($unit) {
            'minute' => $now->greaterThanOrEqualTo($reference->copy()->addMinutes($value)),
            'hour' => $now->greaterThanOrEqualTo($reference->copy()->addHours($value)),
            'day' => $now->greaterThanOrEqualTo($reference->copy()->addDays($value)),
            'week' => $now->greaterThanOrEqualTo($reference->copy()->addWeeks($value)),
            'month' => $now->greaterThanOrEqualTo($reference->copy()->addMonths($value)),
            default => $now->greaterThanOrEqualTo($reference->copy()->addDays(5)),
        };
    }

    /**
     * Check if an event is in the near-event invitation window (within 36 hours before event up to 4 hours after).
     *
     * @param  string|null  $eventDate  Event date (Y-m-d)
     * @param  string|null  $eventTime  Event time (H:i)
     */
    public static function isNearEventInvitationDue(
        ?string $eventDate,
        ?string $eventTime,
        ?string $autoEntryLastSentAt,
        ?Carbon $now = null
    ): bool {
        if (! $eventDate) {
            return false;
        }

        // Already received entry invitation
        if (! empty($autoEntryLastSentAt)) {
            return false;
        }

        $now = $now ?? Carbon::now();
        $dateStr = substr((string) $eventDate, 0, 10);
        $timeStr = ! empty($eventTime) ? (string) $eventTime : '00:00';
        $eventDateTime = Carbon::parse($dateStr.' '.$timeStr);

        // Near-event window: within 36 hours before event up to 4 hours after event start
        $hoursRemaining = $now->diffInHours($eventDateTime, false);

        return $hoursRemaining >= -4 && $hoursRemaining <= 36;
    }
}
