<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\Controller;
use App\Models\ClassParticipant;
use App\Models\LiveClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PresenceController extends Controller
{
    /** Student heartbeat — fires every ~15s while the live page is open. */
    public function heartbeat(Request $request, LiveClass $class)
    {
        $user = Auth::user();
        $groupIds = $user->groups->pluck('id');
        abort_unless($groupIds->contains($class->group_id), 403);
        abort_unless($class->isLive(), 403, 'This class is not live.');

        $participant = ClassParticipant::firstOrCreate(
            ['live_class_id' => $class->id, 'user_id' => $user->id],
            ['joined_at' => now(), 'last_seen_at' => now()]
        );
        $participant->update(['last_seen_at' => now()]);

        return response()->json(['ok' => true, 'at' => now()->toIso8601String()]);
    }

    /** Coach polls this — returns a map of user_id => online (bool) for the group roster. */
    public function roster(Request $request, LiveClass $class)
    {
        abort_unless($class->coach_id === Auth::id(), 403);

        $cutoff = now()->subSeconds(30);

        $rows = ClassParticipant::where('live_class_id', $class->id)
            ->get(['user_id', 'last_seen_at', 'joined_at']);

        $byId = [];
        foreach ($rows as $r) {
            $byId[$r->user_id] = [
                'online' => $r->last_seen_at && $r->last_seen_at->gte($cutoff),
                'last_seen_at' => $r->last_seen_at?->toIso8601String(),
                'joined_at' => $r->joined_at?->toIso8601String(),
            ];
        }

        // Also include any roster student that has never joined yet (online=false).
        $class->loadMissing('group.students');
        foreach ($class->group?->students ?? [] as $s) {
            $byId[$s->id] = $byId[$s->id] ?? ['online' => false, 'last_seen_at' => null, 'joined_at' => null];
        }

        $online = collect($byId)->where('online', true)->count();

        return response()->json([
            'students' => $byId,
            'online' => $online,
            'roster' => $class->group?->students->count() ?? 0,
        ]);
    }
}
