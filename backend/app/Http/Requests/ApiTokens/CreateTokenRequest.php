<?php

namespace BlastSpec\Http\Requests\ApiTokens;

use Illuminate\Foundation\Http\FormRequest;

final class CreateTokenRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'scopes' => ['required', 'array', 'min:1'],
        ];
    }

    /** @return array<int, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Token name is required.',
            'name.min' => 'Token name must be at least 2 characters.',
            'name.max' => 'Token name must be at most 80 characters.',
            'scopes.required' => 'At least one token scope is required.',
            'scopes.min' => 'At least one token scope is required.',
        ];
    }
}
