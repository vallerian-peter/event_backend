<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Committee\CreateCommitteeMeetingNoteRequest;
use App\Http\Requests\Committee\CreateCommitteeMeetingRequest;
use App\Http\Requests\Committee\CreateCommitteeMemberRequest;
use App\Http\Requests\Committee\UpdateCommitteeMemberRequest;
use App\Services\CommitteeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommitteeController extends Controller
{
    public function __construct(
        protected CommitteeService $committeeService
    ) {}

    /**
     * List committee members for an event.
     *
     * GET /api/v1/events/{eventId}/committee/members
     */
    public function indexMembers(Request $request, int $eventId): JsonResponse
    {
        try {
            $members = $this->committeeService->listMembers($eventId, $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Committee members retrieved successfully.',
                'data' => $members,
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Event not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }

    /**
     * Add a committee member.
     *
     * POST /api/v1/events/{eventId}/committee/members
     */
    public function storeMember(CreateCommitteeMemberRequest $request, int $eventId): JsonResponse
    {
        try {
            $member = $this->committeeService->addMember($eventId, $request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Committee member added successfully.',
                'data' => $member,
            ], 201);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Event not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }

    /**
     * Update a committee member.
     *
     * PUT/PATCH /api/v1/events/{eventId}/committee/members/{id}
     */
    public function updateMember(UpdateCommitteeMemberRequest $request, int $eventId, int $id): JsonResponse
    {
        try {
            $member = $this->committeeService->updateMember($id, $request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Committee member updated successfully.',
                'data' => $member,
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Committee member not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }

    /**
     * Delete a committee member.
     *
     * DELETE /api/v1/events/{eventId}/committee/members/{id}
     */
    public function destroyMember(Request $request, int $eventId, int $id): JsonResponse
    {
        try {
            $this->committeeService->deleteMember($id, $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Committee member removed successfully.',
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Committee member not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }

    /**
     * List committee meetings for an event.
     *
     * GET /api/v1/events/{eventId}/committee/meetings
     */
    public function indexMeetings(Request $request, int $eventId): JsonResponse
    {
        try {
            $meetings = $this->committeeService->listMeetings($eventId, $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Committee meetings retrieved successfully.',
                'data' => $meetings,
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Event not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }

    /**
     * Schedule a committee meeting.
     *
     * POST /api/v1/events/{eventId}/committee/meetings
     */
    public function storeMeeting(CreateCommitteeMeetingRequest $request, int $eventId): JsonResponse
    {
        try {
            $meeting = $this->committeeService->createMeeting($eventId, $request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Committee meeting scheduled successfully.',
                'data' => $meeting,
            ], 201);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Event not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }

    /**
     * Add note / minutes to a meeting.
     *
     * POST /api/v1/events/{eventId}/committee/meetings/{id}/notes
     */
    public function storeNote(CreateCommitteeMeetingNoteRequest $request, int $eventId, int $id): JsonResponse
    {
        try {
            $note = $this->committeeService->addMeetingNote($id, $request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Meeting note recorded successfully.',
                'data' => $note,
            ], 201);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Meeting not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }

    /**
     * Get single meeting details.
     *
     * GET /api/v1/events/{eventId}/committee/meetings/{meetingId}
     */
     public function showMeeting(Request $request, int $eventId, int $meetingId): JsonResponse
     {
         try {
             $meeting = $this->committeeService->getMeeting($meetingId, $request->user());

             return response()->json([
                 'status' => 'success',
                 'message' => 'Meeting details retrieved successfully.',
                 'data' => $meeting,
             ]);
         } catch (ModelNotFoundException) {
             return response()->json(['status' => 'error', 'message' => 'Meeting not found.'], 404);
         } catch (AuthorizationException $e) {
             return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
         }
     }

    /**
     * Send meeting reminders to members.
     *
     * POST /api/v1/events/{eventId}/committee/meetings/{id}/remind
     */
    public function sendReminders(Request $request, int $eventId, int $id): JsonResponse
    {
        return $this->sendMeetingReminders($request, $eventId, $id);
    }

    /**
     * Send meeting reminders alias.
     */
    public function sendMeetingReminders(Request $request, int $eventId, int $meetingId): JsonResponse
    {
        try {
            $result = $this->committeeService->sendMeetingReminders($meetingId, $request->user());

            return response()->json([
                'status' => 'success',
                'message' => "Reminders dispatched to {$result['sentCount']} committee member(s).",
                'data' => $result,
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Meeting not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }
}
