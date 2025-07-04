<?php

namespace Tests\Models;

use Illuminate\Database\Eloquent\Model;
use KobyKorman\Eloquentify\EloquentifiesQueries;

class User extends Model
{
    use EloquentifiesQueries;

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
