<?php

namespace App\Models;

use Database\Factories\RepositoryFileCacheFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepositoryFileCache extends Model
{
    /** @use HasFactory<RepositoryFileCacheFactory> */
    use HasFactory;

    protected $table = 'repository_file_cache';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'repository_id',
        'branch',
        'commit_sha',
        'files',
        'cached_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'files' => 'array',
            'cached_at' => 'datetime',
        ];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }
}
