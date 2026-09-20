<?php

namespace App\Bots\Yaarpool\Http\Controllers;

use App\Bots\Yaarpool\Enums\RideType;
use App\Bots\Yaarpool\Models\Ride;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class RidesController extends Controller
{
    public function index(Request $request): View
    {
        $type = RideType::tryFrom((string) $request->query('type'));
        $search = trim((string) $request->query('search'));
        $now = Carbon::now();

        $query = Ride::query()
            ->where('departs_at', '>=', $now);

        if ($type !== null) {
            $query->where('type', $type);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('from_location', 'like', "%{$search}%")
                    ->orWhere('to_location', 'like', "%{$search}%")
                    ->orWhere('chat_jid', 'like', "%{$search}%")
                    ->orWhere('sender_name', 'like', "%{$search}%");
            });
        }

        $rides = $query->get()
            ->sortBy(fn (Ride $ride): Carbon => $ride->departs_at)
            ->values();

        return view('yaarpool.rides.index', [
            'rides' => $rides,
            'type' => $type,
            'search' => $search,
        ]);
    }

    public function show(Ride $ride): View
    {
        $ride->load('passengers');

        return view('yaarpool.rides.show', [
            'ride' => $ride,
        ]);
    }

    public function destroy(Ride $ride): RedirectResponse
    {
        $ride->delete();

        return redirect()
            ->route('yaarpool.rides.index')
            ->with('status', sprintf('Ride #%d cancelled: %s → %s.', $ride->id, $ride->from_location, $ride->to_location));
    }
}
