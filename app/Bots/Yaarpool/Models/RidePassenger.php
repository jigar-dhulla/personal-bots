<?php

declare(strict_types=1);

namespace App\Bots\Yaarpool\Models;

use Database\Factories\Yaarpool\RidePassengerFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(RidePassengerFactory::class)]
class RidePassenger extends Model
{
    /** @use HasFactory<RidePassengerFactory> */
    use HasFactory;

    protected $fillable = [
        'ride_id',
        'sender_jid',
        'sender_name',
        'seats',
    ];

    protected function casts(): array
    {
        return [
            'seats' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Ride, $this>
     */
    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }
}
