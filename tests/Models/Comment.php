<?php

namespace Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Korman\Eloquentize\EloquentizesQueries;

class Comment extends Model
{
    use EloquentizesQueries;

    protected $fillable = [
        'post_id',
        'body',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
