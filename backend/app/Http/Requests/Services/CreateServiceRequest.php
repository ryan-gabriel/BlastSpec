<?php

namespace BlastSpec\Http\Requests\Services;

use Illuminate\Foundation\Http\FormRequest;

final class CreateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
            'owner_team' => ['required', 'string', 'min:2', 'max:80'],
            'repo_url' => ['nullable', 'string', 'url', 'max:255'],
            'base_url' => ['nullable', 'string', 'url', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Service name is required.',
            'name.regex' => 'Service name must be lowercase letters, digits, and hyphens.',
            'owner_team.required' => 'Owner team is required.',
        ];
    }
}
