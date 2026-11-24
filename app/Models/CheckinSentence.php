<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckinSentence extends Model
{
    protected $fillable = ['checkin_id', 'content', 'meaning', 'note', 'position'];

    public function checkin(): BelongsTo
    {
        return $this->belongsTo(Checkin::class);
    }
}
