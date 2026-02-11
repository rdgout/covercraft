<?php

namespace App\Http\Requests;

use App\Models\Repository;
use Illuminate\Foundation\Http\FormRequest;

class DeleteRepositoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $repository = $this->route('repository');

        return $repository instanceof Repository
            && auth()->user()->hasAccessToRepository($repository);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
        ];
    }
}
