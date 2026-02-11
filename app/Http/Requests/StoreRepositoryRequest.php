<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRepositoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->teams()->exists();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'owner' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'default_branch' => ['required', 'string', 'max:255'],
        ];
    }
}
