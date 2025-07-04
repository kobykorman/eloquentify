<?php

namespace Tests\Models;

use Illuminate\Database\Eloquent\Model;
use KobyKorman\Eloquentize\EloquentizesQueries;

class Profile extends Model
{
    use EloquentizesQueries;

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
