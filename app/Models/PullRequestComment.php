<?php

namespace App\Models;

use Database\Factories\PullRequestCommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PullRequestComment extends Model
{
    /** @use HasFactory<PullRequestCommentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'repository_id',
        'pr_number',
        'github_comment_id',
    ];

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }
}
