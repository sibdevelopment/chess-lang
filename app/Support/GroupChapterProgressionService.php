<?php

namespace App\Support;

use App\Models\Chapter;
use App\Models\Group;
use App\Models\LiveClass;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GroupChapterProgressionService
{
    public function levelOrder(): array
    {
        return Chapter::LEVELS;
    }

    public function orderedChapters(Group $group): Collection
    {
        if ($group->relationLoaded('chapters')) {
            return $group->chapters->sortBy('pivot.sequence_order')->values();
        }

        return $group->chapters()->orderByPivot('sequence_order')->get();
    }

    public function completedChapters(Group $group): Collection
    {
        $pointer = max(0, (int) $group->unlocked_chapter_index);

        return $this->orderedChapters($group)
            ->filter(fn (Chapter $chapter) => (int) $chapter->pivot->sequence_order < $pointer)
            ->values();
    }

    public function currentChapter(Group $group): ?Chapter
    {
        return $this->orderedChapters($group)
            ->first(fn (Chapter $chapter) => (int) $chapter->pivot->sequence_order === (int) $group->unlocked_chapter_index);
    }

    public function nextChapter(Group $group): ?Chapter
    {
        return $this->currentChapter($group);
    }

    public function upcomingChapter(Group $group): ?Chapter
    {
        return $this->chapterAtOffset($group, 1);
    }

    public function chapterAtOffset(Group $group, int $offset): ?Chapter
    {
        $target = (int) $group->unlocked_chapter_index + $offset;

        return $this->orderedChapters($group)
            ->first(fn (Chapter $chapter) => (int) $chapter->pivot->sequence_order === $target);
    }

    public function assignStartingPoint(Group $group, string $startingLevel, int $startingChapterId): void
    {
        $orderedCurriculum = $this->orderedCurriculumFromStartingPoint($startingLevel, $startingChapterId);

        if ($orderedCurriculum->isEmpty()) {
            abort(422, 'No curriculum chapter exists yet for the selected starting point.');
        }

        $sync = [];
        foreach ($orderedCurriculum as $index => $chapter) {
            $sync[$chapter->id] = ['sequence_order' => $index];
        }

        $group->chapters()->sync($sync);
        $group->update([
            'level' => $orderedCurriculum->first()->level,
            'unlocked_chapter_index' => 0,
        ]);
        $group->unsetRelation('chapters');
        $group->load('chapters');
    }

    public function orderedCurriculumFromStartingPoint(string $startingLevel, int $startingChapterId): Collection
    {
        $levels = $this->levelOrder();
        $startIndex = array_search($startingLevel, $levels, true);
        if ($startIndex === false) {
            throw ValidationException::withMessages([
                'starting_level' => 'Invalid starting level.',
            ]);
        }

        $startingChapter = Chapter::query()->findOrFail($startingChapterId);
        if ($startingChapter->level !== $startingLevel) {
            throw ValidationException::withMessages([
                'starting_chapter_id' => 'Selected chapter does not match the chosen level.',
            ]);
        }

        $curriculum = collect();
        foreach (array_slice($levels, $startIndex) as $levelIndex => $level) {
            $chapters = Chapter::where('level', $level)->orderedForLevel()->get()->values();
            if ($chapters->isEmpty()) {
                continue;
            }

            if ($levelIndex === 0) {
                $startingOffset = $chapters->search(fn (Chapter $chapter) => (int) $chapter->id === $startingChapter->id);
                if ($startingOffset === false) {
                    throw ValidationException::withMessages([
                        'starting_chapter_id' => 'Selected chapter could not be located in the curriculum.',
                    ]);
                }
                $chapters = $chapters->slice($startingOffset)->values();
            }

            $curriculum = $curriculum->concat($chapters);
        }

        return $curriculum->values();
    }

    public function chapterState(Group $group, Chapter $chapter): string
    {
        $sequence = (int) ($chapter->pivot->sequence_order
            ?? $group->chapters()->where('chapters.id', $chapter->id)->value('chapter_group.sequence_order'));
        $pointer = (int) $group->unlocked_chapter_index;

        if ($sequence < $pointer) {
            return 'completed';
        }

        if ($sequence === $pointer) {
            return 'current';
        }

        return 'locked';
    }

    public function isAccessible(Group $group, Chapter $chapter, bool $allowCompleted = false): bool
    {
        $state = $this->chapterState($group, $chapter);

        return $state === 'current' || ($allowCompleted && $state === 'completed');
    }

    public function markCurrentChapterComplete(Group $group, int $completedBy, ?LiveClass $liveClass = null): array
    {
        return DB::transaction(function () use ($group, $completedBy, $liveClass) {
            $lockedGroup = Group::query()
                ->with('chapters')
                ->lockForUpdate()
                ->findOrFail($group->id);

            $currentChapter = $this->currentChapter($lockedGroup);

            if (!$currentChapter) {
                return [
                    'state' => 'curriculum-complete',
                    'group' => $lockedGroup,
                    'chapter' => null,
                    'completion' => null,
                ];
            }

            if ($liveClass) {
                abort_unless((int) $liveClass->group_id === (int) $lockedGroup->id, 422, 'The selected class does not belong to this group.');
                abort_unless((int) $liveClass->chapter_index === (int) $lockedGroup->unlocked_chapter_index, 422, 'This class is not on the current curriculum chapter.');
                abort_unless((int) $liveClass->chapter_id === (int) $currentChapter->id, 422, 'This class is not using the current curriculum chapter.');
            }

            $completion = $lockedGroup->chapterCompletions()
                ->where('chapter_id', $currentChapter->id)
                ->first();

            if ($completion) {
                return [
                    'state' => 'already-completed',
                    'group' => $lockedGroup,
                    'chapter' => $currentChapter,
                    'completion' => $completion,
                ];
            }

            $completion = $lockedGroup->chapterCompletions()->create([
                'chapter_id' => $currentChapter->id,
                'completed_by' => $completedBy,
                'live_class_id' => $liveClass?->id,
                'completed_at' => now(),
            ]);

            $nextPointer = $this->nextAvailablePointer($lockedGroup, (int) $lockedGroup->unlocked_chapter_index);
            $nextChapter = $this->orderedChapters($lockedGroup)
                ->first(fn (Chapter $chapter) => (int) $chapter->pivot->sequence_order === $nextPointer);

            $lockedGroup->update([
                'unlocked_chapter_index' => $nextPointer,
                'level' => $nextChapter?->level ?? $currentChapter->level,
            ]);

            return [
                'state' => 'completed',
                'group' => $lockedGroup->fresh(),
                'chapter' => $currentChapter,
                'completion' => $completion,
            ];
        });
    }

    protected function nextAvailablePointer(Group $group, int $currentPointer): int
    {
        $nextAvailable = $this->orderedChapters($group)
            ->filter(fn (Chapter $chapter) => (int) $chapter->pivot->sequence_order > $currentPointer)
            ->sortBy(fn (Chapter $chapter) => (int) $chapter->pivot->sequence_order)
            ->first();

        return $nextAvailable
            ? (int) $nextAvailable->pivot->sequence_order
            : $currentPointer + 1;
    }
}
