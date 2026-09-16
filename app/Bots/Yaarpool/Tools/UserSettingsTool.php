<?php

declare(strict_types=1);

namespace App\Bots\Yaarpool\Tools;

use App\Bots\Yaarpool\Models\UserSetting;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UserSettingsTool implements Tool
{
    public function __construct(
        protected ?string $senderJid = null,
    ) {}

    public function name(): string
    {
        return 'user_settings';
    }

    public function description(): Stringable|string
    {
        return 'Save or show the user\'s personal ride defaults — their usual starting/ending location, office hours, and the days they commute (handy for hybrid schedules). Use this when the user asks to remember their usual route, work hours, or travel days, or asks what their saved defaults are. Pass only the fields they stated; pass nothing to show the current defaults.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($this->senderJid === null) {
            return 'I cannot verify who you are right now, so I can\'t manage your personal defaults.';
        }

        $from = $request['from'] ?? null;
        $to = $request['to'] ?? null;

        try {
            $officeStart = $request->date('office_start_time');
        } catch (InvalidFormatException) {
            return 'I could not work out that start time. Try something like "9am" or "09:30".';
        }

        try {
            $officeEnd = $request->date('office_end_time');
        } catch (InvalidFormatException) {
            return 'I could not work out that end time. Try something like "6pm" or "18:00".';
        }

        $officeDays = UserSetting::normalizeOfficeDays($request['office_days'] ?? null);

        $settings = UserSetting::forSender($this->senderJid);

        $hasInput = filled($from) || filled($to) || $officeStart !== null || $officeEnd !== null || $officeDays !== null;

        if (! $hasInput) {
            if ($settings === null) {
                return 'You have no personal defaults saved yet. Tell me your usual route (e.g. "I usually travel from Wakad to Hinjewadi") and I\'ll remember it — I can also save your office hours and travel days.';
            }

            return $this->summarise($settings, 'Your saved defaults');
        }

        if ($settings === null && blank($from)) {
            return 'What is your usual starting location? I need that before I can save your defaults.';
        }

        $settings ??= new UserSetting(['sender_jid' => $this->senderJid]);
        $hadOfficeSchedule = $settings->hasOfficeSchedule();

        if (filled($from)) {
            $settings->default_from_location = (string) $from;
        }

        if (filled($to)) {
            $settings->default_to_location = (string) $to;
        }

        if ($officeStart !== null) {
            $settings->office_start_time = $officeStart->format('H:i:s');
        }

        if ($officeEnd !== null) {
            $settings->office_end_time = $officeEnd->format('H:i:s');
        }

        if ($officeDays !== null) {
            $settings->office_days = $officeDays;
        }

        $settings->save();

        $summary = $this->summarise($settings, 'Saved').' I\'ll use these when you don\'t mention a location.';

        if ((filled($from) || filled($to)) && ! $hadOfficeSchedule && ! $settings->hasOfficeSchedule()) {
            $summary .= ' Want me to remember your office hours and travel days too? Just tell me, e.g. "I work 9 to 6, Mon–Fri."';
        }

        return $summary;
    }

    protected function summarise(UserSetting $settings, string $prefix): string
    {
        $route = $settings->default_to_location === null
            ? sprintf('usually starting from %s', $settings->default_from_location)
            : sprintf('usually %s → %s', $settings->default_from_location, $settings->default_to_location);

        $hours = $settings->officeHoursLabel();
        $days = $settings->officeDaysLabel();

        $work = collect([
            $hours === null ? null : "office hours {$hours}",
            $days === null ? null : "in office {$days}",
        ])->filter()->implode(', ');

        return $work === ''
            ? sprintf('%s: %s.', $prefix, $route)
            : sprintf('%s: %s. %s.', $prefix, $route, ucfirst($work));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()
                ->description('The user\'s usual starting location (e.g. "Wakad", "Sector 21 gate"). Omit when the user is not setting it.'),
            'to' => $schema->string()
                ->description('The user\'s usual destination (e.g. "Hinjewadi", "the airport"). Omit when the user is not setting it.'),
            'office_start_time' => $schema->string()
                ->description('When the user starts work, in whatever form they said it (e.g. "9am", "09:30"). Omit when not stated.'),
            'office_end_time' => $schema->string()
                ->description('When the user finishes work (e.g. "6pm", "18:00"). Omit when not stated.'),
            'office_days' => $schema->array()
                ->items($schema->string()->enum([
                    'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday', 'daily',
                ]))
                ->description('Which days per week the user commutes to the office — common for hybrid schedules, e.g. ["monday","wednesday","friday"]. Use ["daily"] for every weekday. Omit when not stated.'),
        ];
    }
}
