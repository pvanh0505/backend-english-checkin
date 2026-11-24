<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * checkin_date / learned_date được giữ dạng chuỗi 'Y-m-d' (không cast)
 * để so sánh ngày đơn giản, không bị lệch múi giờ.
 */
class Checkin extends Model
{
    protected $fillable = ['user_id', 'checkin_date', 'learned_date', 'note', 'sentence_count'];

    protected function casts(): array
    {
        return ['sentence_count' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sentences(): HasMany
    {
        return $this->hasMany(CheckinSentence::class)->orderBy('position');
    }
}
