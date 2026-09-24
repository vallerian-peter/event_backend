<?php

namespace App\Http\Requests\Committee;

use Illuminate\Foundation\Http\FormRequest;

class CreateCommitteeMeetingRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:220'],
            'meetingDate' => ['required', 'date'],
            'meetingTime' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:220'],
            'intervalCount' => ['nullable', 'integer', 'min:1'],
            'intervalUnit' => ['nullable', 'string', 'in:days,weeks,months'],
            'nextMeetingDate' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:scheduled,completed,cancelled'],
        ];
    }
}
