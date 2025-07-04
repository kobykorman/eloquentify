<?php

namespace Tests\Models;

use Illuminate\Database\Eloquent\Model;
use KobyKorman\Eloquentify\EloquentifiesQueries;

class Comment extends Model
{
    use EloquentifiesQueries;

    protected $fillable = [
        'post_id',
        'body',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
