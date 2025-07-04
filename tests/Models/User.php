<?php

namespace Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Korman\Eloquentize\EloquentizesQueries;

class User extends Model
{
    use EloquentizesQueries;

    protected $fillable = [
        'name',
        'email',
    ];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }
}
