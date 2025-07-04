<?php

namespace Tests\Models;

use Illuminate\Database\Eloquent\Model;
use KobyKorman\Eloquentify\EloquentifiesQueries;

class Tag extends Model
{
    use EloquentifiesQueries;

    protected $fillable = [
        'name',
    ];

    public function posts()
    {
        return $this->belongsToMany(Post::class);
    }
}
