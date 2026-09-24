<?php

namespace App\Http\Requests\Committee;

use Illuminate\Foundation\Http\FormRequest;

class CreateCommitteeMeetingNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string'],
            'type' => ['nullable', 'string', 'in:note,todo,decision'],
            'assignedMemberId' => ['nullable', 'integer', 'exists:event_committee_members,id'],
            'status' => ['nullable', 'string', 'in:open,done,cancelled'],
        ];
    }
}
