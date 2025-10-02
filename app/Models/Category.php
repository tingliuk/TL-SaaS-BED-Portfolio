<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'categories';

    protected $fillable = [
        'title',
        'description',
    ];

    public function jokes(): BelongsToMany
    {
        return $this->belongsToMany(Joke::class);
    }

    /**
     * Returns the collection of related jokes in reverse
     * order of their title.
     */
    public function jokesByTitleDesc(): BelongsToMany
    {
        return $this->jokes()->orderBy('title', 'desc');
    }

    public function jokesByTitle(): BelongsToMany
    {
        return $this->jokes()->orderBy('title');
    }

    /**
     * Returns the collection of related jokes in reverse order
     * of their creation date
     */
    public function jokesByDateAddedDesc(): BelongsToMany
    {
        return $this->jokes()->orderBy('created_at', 'desc');
    }
}
