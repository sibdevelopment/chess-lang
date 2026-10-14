<?php

namespace App\Http\Middleware;

use App\Models\TournamentPairing;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveTournamentGame
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->role !== 'student') {
            return $next($request);
        }

        if ($request->routeIs(
            'student.tournaments.play',
            'student.tournaments.poll',
            'student.tournaments.move',
            'student.tournaments.resign',
            'student.tournaments.draw',
            'student.tournaments.spectate-poll',
            'student.tournaments.download-pgn',
            'student.tournaments.join',
            'student.active-game-check',
            'logout'
        )) {
            return $next($request);
        }

        $pairing = TournamentPairing::with('tournament')
            ->where('status', 'active')
            ->where('is_bye', false)
            ->where(function ($q) use ($user) {
                $q->where('white_user_id', $user->id)
                  ->orWhere('black_user_id', $user->id);
            })
            ->latest('id')
            ->first();

        if ($pairing && $pairing->tournament) {
            return redirect()->route('student.tournaments.play', [$pairing->tournament, $pairing]);
        }

        return $next($request);
    }
}
