<?php

namespace Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Korman\Eloquentize\EloquentizesQueries;

class Tag extends Model
{
    use EloquentizesQueries;

    protected $fillable = [
        'name',
    ];

    public function posts()
    {
        return $this->belongsToMany(Post::class);
    }
}
