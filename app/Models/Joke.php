<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Joke extends Model
{
    /** @use HasFactory<\Database\Factories\JokeFactory> */
    use HasFactory;

    protected $table = 'jokes';

    protected $fillable = [
        'title',
        'content',
        'reference',
        'user_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /**
     * Get the average rating for this joke
     */
    public function getAverageRatingAttribute(): float
    {
        $votes = $this->votes;
        if ($votes->isEmpty()) {
            return 0.0;
        }

        $upVotes = $votes->where('rating', 1)->count();
        $downVotes = $votes->where('rating', -1)->count();
        $totalVotes = $upVotes + $downVotes;

        if ($totalVotes === 0) {
            return 0.0;
        }

        return (($upVotes + $downVotes) - $downVotes) / $totalVotes * 100;
    }
}
