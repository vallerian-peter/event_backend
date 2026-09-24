<?php

namespace App\Http\Requests\Event;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request validating flyer image upload.
 */
class UploadEventFlyerRequest extends FormRequest
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
            'flyer' => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ];
    }
}
