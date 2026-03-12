<?php

namespace App\Jobs;

use App\Models\CoverageReport;
use App\Models\PullRequestComment;
use App\Services\GitHubAppService;
use App\Services\PullRequestCommentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PostPullRequestCommentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $coverageReportId) {}

    public function handle(GitHubAppService $githubAppService, PullRequestCommentService $commentService): void
    {
        if (! $githubAppService->isConfigured()) {
            return;
        }

        $report = CoverageReport::with(['repository', 'files'])->find($this->coverageReportId);

        if (! $report) {
            return;
        }

        $repository = $report->repository;

        $pr = $githubAppService->getOpenPullRequestForBranch($repository, $report->branch);

        if (! $pr) {
            return;
        }

        $prNumber = $pr['number'];

        if (! $report->pr_number) {
            $report->update(['pr_number' => $prNumber]);
        }

        $prComment = PullRequestComment::firstOrCreate([
            'repository_id' => $repository->id,
            'pr_number' => $prNumber,
        ]);

        $baseReport = CoverageReport::query()
            ->where('repository_id', $repository->id)
            ->where('branch', $repository->default_branch)
            ->where('status', 'completed')
            ->where('archived', false)
            ->latest('completed_at')
            ->first();

        $changedFiles = $githubAppService->getPullRequestFiles($repository, $prNumber);

        $body = $commentService->buildCommentBody($report, $baseReport, $changedFiles);

        if ($prComment->github_comment_id) {
            $githubAppService->updatePullRequestComment($repository, $prComment->github_comment_id, $body);
        } else {
            $commentId = $githubAppService->createPullRequestComment($repository, $prNumber, $body);
            $prComment->update(['github_comment_id' => $commentId]);
        }
    }
}
