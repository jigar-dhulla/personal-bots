<?php

namespace App\Bots\Yaarpool\Http\Controllers;

use App\Bots\Yaarpool\Enums\Weekday;
use App\Bots\Yaarpool\Models\UserSetting;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserSettingsController extends Controller
{
    public function index(): View
    {
        $settings = UserSetting::query()->orderBy('sender_jid')->get();

        return view('yaarpool.user-settings.index', [
            'settings' => $settings,
            'weekdays' => Weekday::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sender_jid' => ['required', 'string', 'max:255', 'unique:user_settings,sender_jid'],
            ...$this->rules(),
        ]);

        $setting = UserSetting::query()->create($this->normalize($validated));

        return redirect()
            ->route('yaarpool.user-settings.index')
            ->with('status', sprintf('Saved defaults for %s.', $setting->sender_jid));
    }

    public function update(Request $request, UserSetting $userSetting): RedirectResponse
    {
        $validated = $request->validateWithBag('updateSetting', $this->rules());

        $userSetting->update($this->normalize($validated));

        return redirect()
            ->route('yaarpool.user-settings.index')
            ->with('status', sprintf('Saved defaults for %s.', $userSetting->sender_jid));
    }

    public function destroy(UserSetting $userSetting): RedirectResponse
    {
        $userSetting->delete();

        return redirect()
            ->route('yaarpool.user-settings.index')
            ->with('status', sprintf('Cleared defaults for %s.', $userSetting->sender_jid));
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'default_from_location' => ['required', 'string', 'max:255'],
            'default_to_location' => ['nullable', 'string', 'max:255'],
            'office_start_time' => ['nullable', 'date_format:H:i'],
            'office_end_time' => ['nullable', 'date_format:H:i'],
            'office_days' => ['nullable', 'array'],
            'office_days.*' => [Rule::in(array_column(Weekday::cases(), 'value'))],
        ];
    }

    /**
     * Convert the validated form input into the shape the model expects:
     * "H:i" times get seconds appended, and office days are re-keyed.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalize(array $validated): array
    {
        $validated['office_start_time'] = filled($validated['office_start_time'] ?? null)
            ? $validated['office_start_time'].':00'
            : null;

        $validated['office_end_time'] = filled($validated['office_end_time'] ?? null)
            ? $validated['office_end_time'].':00'
            : null;

        $validated['office_days'] = UserSetting::normalizeOfficeDays($validated['office_days'] ?? null);

        return $validated;
    }
}
