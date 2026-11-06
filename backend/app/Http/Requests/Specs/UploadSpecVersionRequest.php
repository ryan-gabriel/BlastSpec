<?php

namespace BlastSpec\Http\Requests\Specs;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final class UploadSpecVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:5120'],
            'version' => ['required', 'string', 'max:40'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => 'An OpenAPI file is required.',
            'file.max' => 'The spec must be 5 MB or smaller.',
            'version.required' => 'A version label is required.',
        ];
    }

    public function uploadedFile(): ?UploadedFile
    {
        $file = $this->file('file');

        return $file instanceof UploadedFile ? $file : null;
    }
}
