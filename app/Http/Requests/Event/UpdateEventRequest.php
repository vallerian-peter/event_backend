<?php

namespace App\Http\Requests\Event;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request validating Event update.
 */
class UpdateEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'eventCategory' => ['sometimes', 'string', 'in:normal,ticketed'],
            'status' => ['sometimes', 'string', 'in:draft,published,ongoing,completed,cancelled'],
            'typeOfEvent' => ['nullable', 'string', 'max:100'],
            'venueName' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'startDate' => ['nullable', 'date_format:Y-m-d'],
            'endDate' => ['nullable', 'date_format:Y-m-d'],
            'eventTime' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'description' => ['nullable', 'string'],
            'contact1' => ['nullable', 'string', 'max:50'],
            'contact2' => ['nullable', 'string', 'max:50'],
            'templateId' => ['nullable', 'integer', 'exists:templates,id'],
            'invitationTemplateTier' => ['nullable', 'string', 'in:standard,premium'],
            'contributionsNeeded' => ['nullable', 'boolean'],
            'divisions' => ['nullable', 'string', 'in:single,double,both'],
            'divisionsPrices' => ['nullable'],
            'servicePackageSlug' => ['nullable', 'string', 'max:100'],
            'inviteeLimit' => ['nullable', 'integer', 'min:1'],
            'autoSendSms' => ['nullable', 'boolean'],
            'smsTimeDifference' => ['nullable', 'string', 'max:50'],
            'serviceFeatures' => ['nullable'],
            'isPublic' => ['nullable', 'boolean'],
            'isFeatured' => ['nullable', 'boolean'],
            'category' => ['nullable', 'string', 'max:100'],
            'flyer' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'flyerUrl' => ['nullable', 'string', 'url', 'max:500'],
            'coverImageUrl' => ['nullable', 'string', 'url', 'max:500'],
            'bannerUrl' => ['nullable', 'string', 'url', 'max:500'],
            'ticketTypes' => ['nullable', 'array'],
            'ticketTypes.*.name' => ['required_with:ticketTypes', 'string', 'max:255'],
            'ticketTypes.*.price' => ['required_with:ticketTypes', 'numeric', 'min:0'],
            'ticketTypes.*.quantityTotal' => ['required_with:ticketTypes', 'integer', 'min:1'],
            'ticketTypes.*.currency' => ['nullable', 'string', 'max:10'],
            'ticketTypes.*.description' => ['nullable', 'string'],
            'ticketTypes.*.minPerOrder' => ['nullable', 'integer', 'min:1'],
            'ticketTypes.*.maxPerOrder' => ['nullable', 'integer', 'min:1'],
            'ticketTypes.*.salesStartAt' => ['nullable', 'date'],
            'ticketTypes.*.salesEndAt' => ['nullable', 'date'],
            'ticketTypes.*.isActive' => ['nullable', 'boolean'],
        ];
    }
}
