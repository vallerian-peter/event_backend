<?php

namespace App\Services;

use App\Enums\CommitteeMeetingStatus;
use App\Enums\CommitteeMemberStatus;
use App\Enums\CommitteeReminderStatus;
use App\Enums\CommitteeRole;
use App\Enums\CommitteeTitle;
use App\Enums\UserRole;
use App\Jobs\SendSmsJob;
use App\Models\CommitteeMeeting;
use App\Models\CommitteeMeetingNote;
use App\Models\Event;
use App\Models\EventCommitteeMember;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;

class CommitteeService
{
    /**
     * List committee members for an event.
     *
     * @return Collection<int, EventCommitteeMember>
     */
    public function listMembers(int $eventId, ?User $actor = null): Collection
    {
        $event = Event::findOrFail($eventId);
        $this->authorizeAccess($event, $actor);

        return EventCommitteeMember::where('eventId', $eventId)
            ->where('status', '!=', 'removed')
            ->latest('createdAt')
            ->get();
    }

    /**
     * Add a committee member.
     *
     * @param  array<string, mixed>  $data
     */
    public function addMember(int $eventId, array $data, ?User $actor = null): EventCommitteeMember
    {
        $event = Event::findOrFail($eventId);
        $this->authorizeAccess($event, $actor);

        $role = ! empty($data['role']) ? CommitteeRole::tryFrom($data['role']) : null;
        $title = ! empty($data['title']) ? CommitteeTitle::tryFrom($data['title']) : null;
        $status = ! empty($data['status']) ? CommitteeMemberStatus::tryFrom($data['status']) : CommitteeMemberStatus::Active;

        return EventCommitteeMember::create([
            'eventId' => $event->id,
            'fullName' => $data['fullName'],
            'normalPhone' => $data['normalPhone'] ?? null,
            'whatsappPhone' => $data['whatsappPhone'] ?? null,
            'title' => $title,
            'role' => $role,
            'customRole' => $data['customRole'] ?? null,
            'status' => $status ?? CommitteeMemberStatus::Active,
        ]);
    }

    /**
     * Update a committee member.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateMember(int $id, array $data, ?User $actor = null): EventCommitteeMember
    {
        $member = EventCommitteeMember::with('event')->findOrFail($id);
        if ($member->event) {
            $this->authorizeAccess($member->event, $actor);
        }

        if (isset($data['role'])) {
            $data['role'] = CommitteeRole::tryFrom($data['role']);
        }
        if (isset($data['title'])) {
            $data['title'] = CommitteeTitle::tryFrom($data['title']);
        }
        if (isset($data['status'])) {
            $data['status'] = CommitteeMemberStatus::tryFrom($data['status']);
        }

        $member->update($data);

        return $member->fresh();
    }

    /**
     * Delete a committee member.
     */
    public function deleteMember(int $id, ?User $actor = null): void
    {
        $member = EventCommitteeMember::with('event')->findOrFail($id);
        if ($member->event) {
            $this->authorizeAccess($member->event, $actor);
        }

        $member->delete();
    }

    /**
     * List committee meetings for an event.
     *
     * @return Collection<int, CommitteeMeeting>
     */
    public function listMeetings(int $eventId, ?User $actor = null): Collection
    {
        $event = Event::findOrFail($eventId);
        $this->authorizeAccess($event, $actor);

        return CommitteeMeeting::with('notes')
            ->where('eventId', $eventId)
            ->latest('meetingDate')
            ->get();
    }

    /**
     * Schedule a committee meeting.
     *
     * @param  array<string, mixed>  $data
     */
    public function createMeeting(int $eventId, array $data, ?User $actor = null): CommitteeMeeting
    {
        $event = Event::findOrFail($eventId);
        $this->authorizeAccess($event, $actor);

        $status = ! empty($data['status']) ? CommitteeMeetingStatus::tryFrom($data['status']) : CommitteeMeetingStatus::Scheduled;

        return CommitteeMeeting::create([
            'eventId' => $event->id,
            'title' => $data['title'] ?? 'Kikao cha Kamati',
            'meetingDate' => $data['meetingDate'],
            'meetingTime' => $data['meetingTime'] ?? null,
            'location' => $data['location'] ?? null,
            'intervalCount' => $data['intervalCount'] ?? null,
            'intervalUnit' => $data['intervalUnit'] ?? null,
            'nextMeetingDate' => $data['nextMeetingDate'] ?? null,
            'status' => $status ?? CommitteeMeetingStatus::Scheduled,
        ]);
    }

    /**
     * Get a single committee meeting with its notes.
     */
    public function getMeeting(int $meetingId, ?User $actor = null): CommitteeMeeting
    {
        $meeting = CommitteeMeeting::with(['notes', 'event'])->findOrFail($meetingId);
        if ($meeting->event) {
            $this->authorizeAccess($meeting->event, $actor);
        }

        return $meeting;
    }

    /**
     * Add note / minutes to a committee meeting.
     *
     * @param  array<string, mixed>  $data
     */
    public function addMeetingNote(int $meetingId, array $data, ?User $actor = null): CommitteeMeetingNote
    {
        $meeting = CommitteeMeeting::with('event')->findOrFail($meetingId);
        if ($meeting->event) {
            $this->authorizeAccess($meeting->event, $actor);
        }

        return CommitteeMeetingNote::create([
            'meetingId' => $meeting->id,
            'assignedMemberId' => $data['assignedMemberId'] ?? null,
            'createdByUserId' => $actor?->id,
            'content' => $data['content'],
            'type' => $data['type'] ?? 'note',
            'status' => $data['status'] ?? 'open',
        ]);
    }

    /**
     * Send meeting reminders to all active committee members.
     *
     * @return array<string, mixed>
     */
    public function sendMeetingReminders(int $meetingId, ?User $actor = null): array
    {
        $meeting = CommitteeMeeting::with(['event', 'event.committeeMembers'])->findOrFail($meetingId);
        if ($meeting->event) {
            $this->authorizeAccess($meeting->event, $actor);
        }

        $members = EventCommitteeMember::where('eventId', $meeting->eventId)
            ->where('status', 'active')
            ->whereNotNull('normalPhone')
            ->get();

        $sentCount = 0;
        foreach ($members as $member) {
            $phone = preg_replace('/\D/', '', $member->normalPhone ?? '');
            if (str_starts_with($phone, '0')) {
                $phone = '255'.substr($phone, 1);
            } elseif (! str_starts_with($phone, '255')) {
                $phone = '255'.$phone;
            }

            $msg = "Kumbusho la Kikao cha Kamati\n\nHabari {$member->fullName}, tunakukumbusha kuhusu kikao cha kamati cha {$meeting->event->title}.\n\nTarehe: {$meeting->meetingDate}\nMuda: ".($meeting->meetingTime ?: 'TBA')."\nMahali: ".($meeting->location ?: 'TBA')."\n\nTafadhali fika kwa wakati.";

            SendSmsJob::dispatch($phone, $msg);
            $member->update(['lastReminderSentAt' => now()]);
            $sentCount++;
        }

        $meeting->update([
            'reminderStatus' => CommitteeReminderStatus::Sent,
            'lastReminderSentAt' => now(),
        ]);

        return [
            'sentCount' => $sentCount,
            'meetingId' => $meeting->id,
            'status' => 'sent',
        ];
    }

    /**
     * Authorize user access to the event.
     *
     * @throws AuthorizationException
     */
    protected function authorizeAccess(Event $event, ?User $actor): void
    {
        if (! $actor) {
            return;
        }

        if ($actor->role === UserRole::Admin) {
            return;
        }

        if ($actor->role === UserRole::EventOwner && (int) $actor->roleId === (int) $event->eventOwnerId) {
            return;
        }

        throw new AuthorizationException('You are not authorized to manage committee for this event.');
    }
}
