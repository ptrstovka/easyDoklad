<?php

namespace App\Models;

use App\Casts\AsMoney;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property \Brick\Money\Money $amount
 * @property Model $payable
 * @property \App\Models\User $recordedBy
 * @property PaymentMethod $method
 * @property \Carbon\Carbon $received_at
 */
class Payment extends Model
{
    use SoftDeletes;

    protected $guarded = false;

    protected $casts = [
        'amount' => AsMoney::class,
        'method' => PaymentMethod::class,
        'received_at' => 'datetime',
    ];

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
