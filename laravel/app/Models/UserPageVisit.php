<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPageVisit extends Model
{
    protected $fillable = ['user_id', 'route_name', 'path', 'title'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}