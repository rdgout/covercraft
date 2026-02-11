<?php

namespace App\Models;

use Database\Factories\TeamAccessTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamAccessToken extends Model
{
    /** @use HasFactory<TeamAccessTokenFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'created_by_user_id',
        'name',
        'token',
        'last_used_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'token',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public static function findToken(string $plainToken): ?self
    {
        return static::where('token', hash('sha256', $plainToken))->first();
    }

    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}
