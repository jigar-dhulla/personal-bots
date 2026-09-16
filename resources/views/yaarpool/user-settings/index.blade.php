<x-admin.layout title="User settings">
    <header class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <h1 class="font-display text-3xl font-extrabold tracking-tight">User settings</h1>
        <p class="text-sm opacity-70">{{ $settings->count() }} {{ Str::plural('user', $settings->count()) }} with personal defaults</p>
    </header>

    @if ($errors->updateSetting->isNotEmpty())
        <div class="mt-6 rounded-2xl border border-rose-600/20 bg-rose-100 px-5 py-3 text-sm font-medium text-rose-800">
            {{ $errors->updateSetting->first() }}
        </div>
    @endif

    <div class="mt-8 rounded-3xl border border-ink/10 bg-white p-6 shadow-card">
        <h2 class="font-display text-lg font-bold">Add a user</h2>
        <p class="mt-1 text-sm opacity-70">
            Rides posted by this sender will fall back to these locations when the message doesn't mention them. Find the sender JID with <code class="rounded bg-ink/5 px-1.5 py-0.5 font-mono text-xs">php artisan wa:chats</code>.
        </p>

        <form method="POST" action="{{ route('yaarpool.user-settings.store') }}" class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3 sm:items-start">
            @csrf

            <div>
                <label for="sender_jid" class="block text-sm font-bold">Sender JID</label>
                <input id="sender_jid" type="text" name="sender_jid" value="{{ old('sender_jid') }}" required placeholder="919999999999@s.whatsapp.net"
                    class="mt-1.5 w-full rounded-xl border border-ink/15 bg-white px-4 py-2.5 font-mono text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                @error('sender_jid')
                    <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="default_from_location" class="block text-sm font-bold">Default origin</label>
                <input id="default_from_location" type="text" name="default_from_location" value="{{ old('default_from_location') }}" required placeholder="e.g. Wakad"
                    class="mt-1.5 w-full rounded-xl border border-ink/15 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                @error('default_from_location')
                    <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="default_to_location" class="block text-sm font-bold">Default destination <span class="font-medium opacity-60">(optional)</span></label>
                <input id="default_to_location" type="text" name="default_to_location" value="{{ old('default_to_location') }}" placeholder="e.g. Hinjewadi"
                    class="mt-1.5 w-full rounded-xl border border-ink/15 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                @error('default_to_location')
                    <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="office_start_time" class="block text-sm font-bold">Office start <span class="font-medium opacity-60">(optional)</span></label>
                <input id="office_start_time" type="time" name="office_start_time" value="{{ old('office_start_time') }}"
                    class="mt-1.5 w-full rounded-xl border border-ink/15 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                @error('office_start_time')
                    <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="office_end_time" class="block text-sm font-bold">Office end <span class="font-medium opacity-60">(optional)</span></label>
                <input id="office_end_time" type="time" name="office_end_time" value="{{ old('office_end_time') }}"
                    class="mt-1.5 w-full rounded-xl border border-ink/15 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                @error('office_end_time')
                    <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <span class="block text-sm font-bold">Office days <span class="font-medium opacity-60">(optional)</span></span>
                <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1.5">
                    @foreach ($weekdays as $weekday)
                        <label class="flex items-center gap-1.5 text-xs font-medium">
                            <input type="checkbox" name="office_days[]" value="{{ $weekday->value }}" @checked(in_array($weekday->value, old('office_days', []), true))
                                class="rounded border-ink/25 text-emerald-600 focus:ring-emerald-500">
                            {{ $weekday->short() }}
                        </label>
                    @endforeach
                </div>
                @error('office_days')
                    <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-3">
                <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-emerald-500 px-5 py-2.5 text-sm font-bold text-white shadow-card transition hover:-translate-y-0.5 hover:bg-emerald-600 hover:shadow-card-lg">
                    Save defaults
                </button>
            </div>
        </form>
    </div>

    @if ($settings->isEmpty())
        <div class="mt-8 rounded-3xl border border-ink/10 bg-white p-12 text-center shadow-card">
            <p class="font-display text-xl font-bold">No user defaults yet</p>
            <p class="mt-2 text-sm opacity-70">Add a user above, or wait for them to save their route over WhatsApp.</p>
        </div>
    @else
        <div class="mt-8 space-y-4">
            @foreach ($settings as $setting)
                <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-card">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <h2 class="min-w-0 font-mono text-lg font-bold break-words">{{ $setting->sender_jid }}</h2>
                        <form method="POST" action="{{ route('yaarpool.user-settings.destroy', $setting) }}" class="shrink-0" onsubmit="return confirm('Clear the defaults for {{ $setting->sender_jid }}?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center gap-2 rounded-full border border-rose-200 bg-white px-5 py-2.5 text-sm font-bold text-rose-600 shadow-card transition hover:-translate-y-0.5 hover:bg-rose-50 hover:shadow-card-lg">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                Clear
                            </button>
                        </form>
                    </div>

                    <form method="POST" action="{{ route('yaarpool.user-settings.update', $setting) }}" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3 sm:items-end">
                        @csrf
                        @method('PUT')

                        <div>
                            <label for="from-{{ $setting->id }}" class="block text-sm font-bold">Default origin</label>
                            <input id="from-{{ $setting->id }}" type="text" name="default_from_location" value="{{ $setting->default_from_location }}" required
                                class="mt-1.5 w-full rounded-xl border border-ink/15 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        </div>

                        <div>
                            <label for="to-{{ $setting->id }}" class="block text-sm font-bold">Default destination <span class="font-medium opacity-60">(optional)</span></label>
                            <input id="to-{{ $setting->id }}" type="text" name="default_to_location" value="{{ $setting->default_to_location }}"
                                class="mt-1.5 w-full rounded-xl border border-ink/15 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="start-{{ $setting->id }}" class="block text-sm font-bold">Office start</label>
                                <input id="start-{{ $setting->id }}" type="time" name="office_start_time" value="{{ $setting->office_start_time ? substr($setting->office_start_time, 0, 5) : '' }}"
                                    class="mt-1.5 w-full rounded-xl border border-ink/15 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            </div>
                            <div>
                                <label for="end-{{ $setting->id }}" class="block text-sm font-bold">Office end</label>
                                <input id="end-{{ $setting->id }}" type="time" name="office_end_time" value="{{ $setting->office_end_time ? substr($setting->office_end_time, 0, 5) : '' }}"
                                    class="mt-1.5 w-full rounded-xl border border-ink/15 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            </div>
                        </div>

                        <div class="sm:col-span-3">
                            <span class="block text-sm font-bold">Office days</span>
                            <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1.5">
                                @foreach ($weekdays as $weekday)
                                    <label class="flex items-center gap-1.5 text-xs font-medium">
                                        <input type="checkbox" name="office_days[]" value="{{ $weekday->value }}" @checked(in_array($weekday->value, $setting->office_days ?? [], true))
                                            class="rounded border-ink/25 text-emerald-600 focus:ring-emerald-500">
                                        {{ $weekday->short() }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-full border border-ink/10 bg-white px-5 py-2.5 text-sm font-bold shadow-card transition hover:-translate-y-0.5 hover:shadow-card-lg">
                                Save changes
                            </button>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
</x-admin.layout>
