<?php

namespace App\Services\ArcherJourney;

use App\Models\ArcherAchievement;
use App\Models\HomeworkSubmission;
use App\Models\LiveClass;
use App\Models\Puzzle;
use App\Models\StudentPuzzleAttempt;
use App\Models\Tournament;
use App\Models\TournamentPairing;
use PChess\Chess\Chess;
use Throwable;

class ArcherLmsEventBridge
{
    public function classCompleted(LiveClass $class): void
    {
        $class->loadMissing(['participants', 'chapter', 'group']);
        $occurredAt = $class->actualEnd() ?? $class->ended_at ?? now()->utc();

        foreach ($class->participants as $student) {
            app(ArcherJourneyEngine::class)->record(
                ArcherJourneyEngine::CLASS_ATTENDED,
                $student,
                $class,
                [
                    'title' => 'Attended '.$class->name,
                    'class_name' => $class->name,
                    'group_id' => $class->group_id,
                    'chapter_id' => $class->chapter_id,
                ],
                $class->coach_id,
                $occurredAt,
                $class->displayTimezone(),
                'class_attended:'.$student->id.':'.$class->id,
            );
        }
    }

    public function homeworkCompleted(HomeworkSubmission $submission): void
    {
        $submission->loadMissing(['student', 'homework.group']);
        $student = $submission->student;
        if (! $student || $submission->status !== 'done') {
            return;
        }

        app(ArcherJourneyEngine::class)->record(
            ArcherJourneyEngine::HOMEWORK_COMPLETED,
            $student,
            $submission->homework,
            [
                'title' => 'Completed '.$submission->homework->title,
                'homework_submission_id' => $submission->id,
                'group_id' => $submission->homework->group_id,
            ],
            null,
            $submission->submitted_at ?? now()->utc(),
            $submission->homework->group?->scheduleTimezone(),
            'homework_completed:'.$student->id.':'.$submission->homework_id,
        );
    }

    public function puzzleCompleted(StudentPuzzleAttempt $attempt, Puzzle $puzzle): void
    {
        $attempt->loadMissing('student');
        $puzzle->loadMissing('puzzleSet.category');
        $student = $attempt->student;
        if (! $student || $attempt->status !== 'completed') {
            return;
        }

        $context = [
            'title' => 'Solved puzzle #'.$puzzle->puzzle_order,
            'puzzle_set_id' => $puzzle->puzzle_set_id,
            'category' => $puzzle->puzzleSet?->category?->name ?? $puzzle->puzzleSet?->category_name,
        ];
        $occurredAt = $attempt->completed_at ?? now()->utc();
        app(ArcherJourneyEngine::class)->record(
            ArcherJourneyEngine::PUZZLE_COMPLETED,
            $student,
            $puzzle,
            $context,
            null,
            $occurredAt,
            null,
            'puzzle_completed:'.$student->id.':'.$puzzle->id,
        );

        if (str_contains(strtolower((string) $context['category']), 'endgame')) {
            app(ArcherJourneyEngine::class)->record(
                ArcherJourneyEngine::ENDGAME_PUZZLE_COMPLETED,
                $student,
                $puzzle,
                $context,
                null,
                $occurredAt,
                null,
                'endgame_puzzle_completed:'.$student->id.':'.$puzzle->id,
            );
        }
    }

    public function tournamentStarted(Tournament $tournament): void
    {
        $tournament->loadMissing('participants.user');
        $countries = $tournament->participants
            ->pluck('user.country')
            ->filter()
            ->unique();
        $international = $countries->count() > 1;

        foreach ($tournament->participants as $participant) {
            $student = $participant->user;
            if (! $student || $participant->withdrawn) {
                continue;
            }

            $context = [
                'title' => 'Played in '.$tournament->name,
                'tournament_name' => $tournament->name,
                'rating_type' => $tournament->format,
                'event_series' => $tournament->event_series,
            ];
            app(ArcherJourneyEngine::class)->record(
                ArcherJourneyEngine::TOURNAMENT_PARTICIPATED,
                $student,
                $tournament,
                $context,
                $tournament->created_by,
                $tournament->scheduled_at,
                $tournament->tournament_timezone,
                'tournament_participated:'.$student->id.':'.$tournament->id,
            );

            if ($international) {
                app(ArcherJourneyEngine::class)->record(
                    ArcherJourneyEngine::INTERNATIONAL_EVENT_PARTICIPATED,
                    $student,
                    $tournament,
                    $context,
                    $tournament->created_by,
                    $tournament->scheduled_at,
                    $tournament->tournament_timezone,
                    'international_event:'.$student->id.':'.$tournament->id,
                );
            }
        }
    }

    public function tournamentGameCompleted(TournamentPairing $pairing): void
    {
        $pairing->loadMissing(['tournament', 'white', 'black']);
        if (! $pairing->isComplete() || $pairing->is_bye) {
            return;
        }

        $isCheckmate = $this->isCheckmate($pairing);
        foreach ([$pairing->white, $pairing->black] as $student) {
            if (! $student) {
                continue;
            }

            $context = [
                'title' => 'Played '.$pairing->tournament->name.' round '.$pairing->round,
                'tournament_id' => $pairing->tournament_id,
                'pairing_id' => $pairing->id,
                'round' => $pairing->round,
                'result' => $pairing->result,
            ];
            app(ArcherJourneyEngine::class)->record(
                ArcherJourneyEngine::TOURNAMENT_GAME_PLAYED,
                $student,
                $pairing,
                $context,
                null,
                $pairing->completed_at,
                $pairing->tournament->tournament_timezone,
                'tournament_game:'.$student->id.':'.$pairing->id,
            );
        }

        $winner = $pairing->winner;
        if (! $winner) {
            return;
        }

        app(ArcherJourneyEngine::class)->record(
            ArcherJourneyEngine::TOURNAMENT_WON,
            $winner,
            $pairing,
            [
                'title' => 'Won '.$pairing->tournament->name.' round '.$pairing->round,
                'tournament_id' => $pairing->tournament_id,
                'pairing_id' => $pairing->id,
            ],
            null,
            $pairing->completed_at,
            $pairing->tournament->tournament_timezone,
            'tournament_win:'.$winner->id.':'.$pairing->id,
        );

        if ($isCheckmate) {
            app(ArcherJourneyEngine::class)->record(
                ArcherJourneyEngine::TOURNAMENT_CHECKMATE,
                $winner,
                $pairing,
                ['title' => 'Delivered checkmate in '.$pairing->tournament->name],
                null,
                $pairing->completed_at,
                $pairing->tournament->tournament_timezone,
                'tournament_checkmate:'.$winner->id.':'.$pairing->id,
            );
        }
    }

    public function achievementCreated(ArcherAchievement $achievement): void
    {
        $achievement->loadMissing(['student', 'awarder']);
        if (! $achievement->student) {
            return;
        }

        $event = app(ArcherJourneyEngine::class)->record(
            ArcherJourneyEngine::ACHIEVEMENT_CREATED,
            $achievement->student,
            $achievement,
            [
                'title' => $achievement->title,
                'description' => $achievement->note,
                'achievement_type' => $achievement->achievement_type,
            ],
            $achievement->awarder,
            $achievement->awarded_at,
            null,
            'achievement:'.$achievement->id,
        );

        if (! $achievement->activity_event_id) {
            $achievement->update(['activity_event_id' => $event->id]);
        }
    }

    protected function isCheckmate(TournamentPairing $pairing): bool
    {
        if (! $pairing->current_fen || ! $pairing->winner_user_id) {
            return false;
        }

        try {
            return (new Chess($pairing->current_fen))->inCheckmate();
        } catch (Throwable) {
            return false;
        }
    }
}
