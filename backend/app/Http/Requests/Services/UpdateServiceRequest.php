<?php

namespace BlastSpec\Http\Requests\Services;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'owner_team' => ['sometimes', 'string', 'min:2', 'max:80'],
            'repo_url' => ['sometimes', 'nullable', 'string', 'url', 'max:255'],
            'base_url' => ['sometimes', 'nullable', 'string', 'url', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'owner_team.min' => 'Owner team must be at least 2 characters.',
        ];
    }
}
