<?php

namespace App\Http\Requests;

use App\Models\CoverageReport;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StoreCoverageDiffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $report = $this->route('report');

        return $report instanceof CoverageReport
            && $report->repository->team_id === $this->attributes->get('authenticated_team_id');
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'diff_file' => ['present', function (string $attribute, mixed $value, Closure $fail): void {
                if (! $value instanceof UploadedFile || ! $value->isValid()) {
                    $fail('A valid diff file upload is required.');
                } elseif ($value->getSize() > (int) config('coverage.diff.max_upload_kib') * 1024) {
                    $fail('The diff exceeds the configured upload limit.');
                }
            }],
            'base_commit_sha' => ['required', 'string', 'regex:/^[a-f0-9]{40}$/'],
            'head_commit_sha' => ['required', 'string', 'regex:/^[a-f0-9]{40}$/', Rule::in([$this->route('report')->commit_sha])],
            'sha256' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'file_count' => ['required', 'integer', 'min:0', 'max:'.config('coverage.diff.max_files')],
            'added_lines' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'deleted_lines' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'base_branch' => ['nullable', 'string', 'max:255'],
            'comparison_type' => ['nullable', Rule::in(['merge_base', 'first_parent', 'initial'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'diff_file.present' => 'The diff file is required.',
            'base_commit_sha.required' => 'The effective diff base commit is required.',
            'base_commit_sha.regex' => 'The base commit must be a lowercase 40-character Git SHA.',
            'head_commit_sha.required' => 'The uploaded coverage commit is required.',
            'head_commit_sha.regex' => 'The head commit must be a lowercase 40-character Git SHA.',
            'head_commit_sha.in' => 'The diff head must match this coverage report\'s commit.',
            'sha256.required' => 'The diff artifact checksum is required.',
            'sha256.regex' => 'The checksum must be a lowercase SHA-256 hash.',
            'file_count.required' => 'The complete diff file count is required.',
            'file_count.max' => 'The diff exceeds the configured file limit.',
            'added_lines.required' => 'The complete diff addition count is required.',
            'deleted_lines.required' => 'The complete diff deletion count is required.',
            'comparison_type.in' => 'Choose merge_base, first_parent, or initial as the comparison type.',
        ];
    }
}
