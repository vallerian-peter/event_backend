<?php

namespace App\Http\Requests\Template;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request validating template background or preview file upload.
 */
class UploadTemplateFileRequest extends FormRequest
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
            'file' => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:10240'],
            'type' => ['nullable', 'string', 'in:template,preview'],
        ];
    }
}
