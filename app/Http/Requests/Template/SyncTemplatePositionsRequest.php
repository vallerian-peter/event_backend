<?php

namespace App\Http\Requests\Template;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request validating quick synchronization of template element positions (QR code, name, etc.).
 */
class SyncTemplatePositionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Flexible payload: array of elements or specific keys
            'elements' => ['nullable', 'array'],
            'elements.*.elementType' => ['required_with:elements', 'string', 'in:qr_code,invitee_name,custom_text,ticket_tier,seat_number'],
            'elements.*.positionX' => ['required_with:elements', 'integer'],
            'elements.*.positionY' => ['required_with:elements', 'integer'],
            'elements.*.width' => ['nullable', 'integer', 'min:1'],
            'elements.*.height' => ['nullable', 'integer', 'min:1'],
            'elements.*.fontSize' => ['nullable', 'integer', 'min:8', 'max:200'],
            'elements.*.fontColor' => ['nullable', 'string', 'max:32'],
            'elements.*.fontFamily' => ['nullable', 'string', 'max:64'],
            'elements.*.alignment' => ['nullable', 'string', 'in:left,center,right'],
            'elements.*.isRequired' => ['nullable', 'boolean'],
            'elements.*.isActive' => ['nullable', 'boolean'],

            // Dedicated QR Code object
            'qrCode' => ['nullable', 'array'],
            'qrCode.positionX' => ['required_with:qrCode', 'integer'],
            'qrCode.positionY' => ['required_with:qrCode', 'integer'],
            'qrCode.width' => ['nullable', 'integer', 'min:1'],
            'qrCode.height' => ['nullable', 'integer', 'min:1'],

            // Dedicated Invitee Name object
            'inviteeName' => ['nullable', 'array'],
            'inviteeName.positionX' => ['required_with:inviteeName', 'integer'],
            'inviteeName.positionY' => ['required_with:inviteeName', 'integer'],
            'inviteeName.fontSize' => ['nullable', 'integer', 'min:8', 'max:200'],
            'inviteeName.fontColor' => ['nullable', 'string', 'max:32'],
            'inviteeName.fontFamily' => ['nullable', 'string', 'max:64'],
            'inviteeName.alignment' => ['nullable', 'string', 'in:left,center,right'],
        ];
    }
}
