<?php

declare(strict_types=1);

namespace App\Bots\Yaarpool\Models;

use App\Bots\Yaarpool\Enums\Weekday;
use Database\Factories\Yaarpool\UserSettingFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

#[UseFactory(UserSettingFactory::class)]
class UserSetting extends Model
{
    /** @use HasFactory<UserSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'sender_jid',
        'default_from_location',
        'default_to_location',
        'office_start_time',
        'office_end_time',
        'office_days',
    ];

    protected function casts(): array
    {
        return [
            'office_days' => 'array',
        ];
    }

    /**
     * The personal defaults saved by a sender, or null when none are set.
     */
    public static function forSender(?string $senderJid): ?self
    {
        if ($senderJid === null) {
            return null;
        }

        return static::query()->where('sender_jid', $senderJid)->first();
    }

    /**
     * Normalize the agent's `office_days` selection (weekday names or "daily")
     * into weekday values ordered Monday → Sunday. Invalid tokens are dropped
     * and duplicates collapsed. Returns null when the input selects no valid day.
     *
     * @param  array<int, string>|null  $input
     * @return array<int, string>|null
     */
    public static function normalizeOfficeDays(?array $input): ?array
    {
        if (empty($input)) {
            return null;
        }

        $weekdays = array_column(Weekday::cases(), 'value');

        if (in_array('daily', $input, true)) {
            return $weekdays;
        }

        $days = array_values(array_intersect($weekdays, $input));

        return $days === [] ? null : $days;
    }

    public function hasOfficeSchedule(): bool
    {
        return $this->office_start_time !== null
            || $this->office_end_time !== null
            || ! empty($this->office_days);
    }

    /**
     * A friendly "9:00 AM–6:00 PM" style label, or null when no hours are set.
     */
    public function officeHoursLabel(): ?string
    {
        $start = $this->office_start_time === null ? null : Carbon::createFromFormat('H:i:s', $this->office_start_time)->format('g:i A');
        $end = $this->office_end_time === null ? null : Carbon::createFromFormat('H:i:s', $this->office_end_time)->format('g:i A');

        return match (true) {
            $start !== null && $end !== null => "{$start}–{$end}",
            $start !== null => "from {$start}",
            $end !== null => "until {$end}",
            default => null,
        };
    }

    /**
     * A WhatsApp-friendly label for the office days (e.g. "daily", "Mon/Wed/Fri").
     */
    public function officeDaysLabel(): ?string
    {
        $days = $this->office_days;

        if (empty($days)) {
            return null;
        }

        if (count($days) === 7) {
            return 'daily';
        }

        return (new Collection($days))
            ->map(fn (string $day): string => Weekday::from($day)->short())
            ->implode('/');
    }
}
