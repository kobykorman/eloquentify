<?php

namespace Tests\Models;

use Illuminate\Database\Eloquent\Model;
use KobyKorman\Eloquentify\EloquentifiesQueries;

class Profile extends Model
{
    use EloquentifiesQueries;

    protected $fillable = [
        'user_id',
        'bio',
        'avatar',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
