<?php

namespace App\Http\Requests;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;

class StoreTeamAccessTokenRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = Team::find($this->input('team_id'));

        return $team && auth()->user()->belongsToTeam($team);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'team_id.required' => 'Please select a team',
            'team_id.exists' => 'Invalid team selected',
            'name.required' => 'Please provide a name for this token',
            'name.max' => 'Token name cannot exceed 255 characters',
        ];
    }
}
