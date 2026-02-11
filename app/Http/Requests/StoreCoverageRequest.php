<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCoverageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'repository' => ['required', 'string'],
            'branch' => ['required', 'string', 'max:255'],
            'commit_sha' => ['required', 'string', 'size:40'],
            'clover_file' => ['required', 'file'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'clover_file.required' => 'The clover.xml file is required',
            'clover_file.file' => 'The clover file must be a valid file upload',
            'branch.required' => 'The branch name is required',
            'commit_sha.required' => 'The commit SHA is required',
            'commit_sha.size' => 'The commit SHA must be 40 characters',
        ];
    }
}
