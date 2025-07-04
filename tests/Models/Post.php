<?php

namespace Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Korman\Eloquentize\EloquentizesQueries;

class Post extends Model
{
    use EloquentizesQueries;

    protected $fillable = [
        'user_id',
        'title',
        'content',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }
}
