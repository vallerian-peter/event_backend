<?php

namespace App\Http\Requests\Template;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request validating Template creation.
 */
class CreateTemplateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:10240'],
            'previewFile' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:10240'],
            'pathUrl' => ['required_without:file', 'nullable', 'string', 'max:500'],
            'previewImageUrl' => ['nullable', 'string', 'max:500'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'isPremium' => ['nullable', 'boolean'],
            'category' => ['nullable', 'string', 'max:100'],
            'templateType' => ['nullable', 'string', 'in:invitation_card,ticket_pass,reminder_card'],
            'canvasWidth' => ['nullable', 'integer', 'min:100', 'max:10000'],
            'canvasHeight' => ['nullable', 'integer', 'min:100', 'max:10000'],
            'renderMode' => ['nullable', 'string', 'in:qr_only,qr_and_name,custom'],
            'isActive' => ['nullable', 'boolean'],

            // Full elements array
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

            // Dedicated shortcuts for QR Code and Invitee Name
            'qrCode' => ['nullable', 'array'],
            'qrCode.positionX' => ['required_with:qrCode', 'integer'],
            'qrCode.positionY' => ['required_with:qrCode', 'integer'],
            'qrCode.width' => ['nullable', 'integer', 'min:1'],
            'qrCode.height' => ['nullable', 'integer', 'min:1'],

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
