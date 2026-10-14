<?php
// ═══════════════════════════════════════════════════════
// app/Models/Group.php
// ═══════════════════════════════════════════════════════
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Support\GroupChapterProgressionService;
use Illuminate\Support\Facades\Schema;

class Group extends Model
{
    use HasFactory;
    public const DEFAULT_TIMEZONE = 'Asia/Kolkata';
    public const DEFAULT_DURATION_MINUTES = 60;
    public const LEVELS = ['beginner', 'intermediate', 'advanced', 'expert'];

    protected $fillable = ['name','country','timezone','start_date','end_date','level','coach_id','created_by','unlocked_chapter_index','description','is_active'];
    protected $casts = ['is_active' => 'boolean', 'start_date' => 'date', 'end_date' => 'date'];

    public function coach()    { return $this->belongsTo(User::class, 'coach_id'); }
    public function creator()  { return $this->belongsTo(User::class, 'created_by'); }
    public function students() { return $this->belongsToMany(User::class, 'group_student', 'group_id', 'user_id')->withTimestamps(); }
    public function chapters() { return $this->belongsToMany(Chapter::class, 'chapter_group')->withPivot('sequence_order')->orderBy('chapter_group.sequence_order'); }
    public function liveClasses() { return $this->hasMany(LiveClass::class); }
    public function schedules() {
        $query = $this->hasMany(GroupSchedule::class);
        if (Schema::hasColumn('group_schedules', 'is_active')) {
            $query->where('is_active', true);
        }
        return $query->orderBy('day_of_week')->orderBy('start_time');
    }
    public function scheduleSlots() { return $this->hasMany(GroupSchedule::class)->orderBy('day_of_week')->orderBy('start_time'); }
    public function homework() { return $this->hasMany(Homework::class); }
    public function chapterCompletions() { return $this->hasMany(GroupChapterCompletion::class); }

    public function scheduleTimezone(): string
    {
        return $this->timezone ?: self::DEFAULT_TIMEZONE;
    }

    /**
     * Chapter for the *next* class to be taught (sequence_order = unlocked_chapter_index).
     * Returns null when the group has reached the end of its level (admin must promote).
     */
    public function getNextChapter()
    {
        return app(GroupChapterProgressionService::class)->nextChapter($this);
    }

    public function getCurrentChapter()
    {
        return app(GroupChapterProgressionService::class)->currentChapter($this);
    }

    public function getUpcomingChapter()
    {
        return app(GroupChapterProgressionService::class)->upcomingChapter($this);
    }

    public function getCurrentCurriculumLevel(): ?string
    {
        return $this->getCurrentChapter()?->level
            ?? $this->getUpcomingChapter()?->level
            ?? $this->level;
    }

    /**
     * Chapter at a specific offset from the current unlocked index.
     * Used by ClassScheduler so each future class instance gets its own chapter:
     *   $offset = 0 → next class
     *   $offset = 1 → class after that
     *   $offset = N → N classes ahead
     * Returns null when the offset goes past the last chapter at this level.
     */
    public function chapterAtOffset(int $offset)
    {
        return app(GroupChapterProgressionService::class)->chapterAtOffset($this, $offset);
    }

    /**
     * Total chapters synced to this group (across the level it started at AND
     * any later levels we've already extended into).
     */
    public function totalChapters(): int
    {
        return (int) $this->chapters()->count();
    }

    /**
     * Has this group exhausted EVERY chapter currently attached, AND there are
     * no further levels left to roll into? Cross-level rollover means a beginner
     * group will continue into intermediate and then advanced. Only when every
     * level's library has been consumed does this return true.
     */
    public function hasFinishedLevel(): bool
    {
        return $this->unlocked_chapter_index >= $this->totalChapters();
    }

    /**
     * If the group has consumed every chapter currently attached, append the
     * full library of the next level (beginner → intermediate → advanced) to
     * the chapter_group pivot with continuing sequence_order numbers. After
     * this call, chapterAtOffset() can keep returning chapters seamlessly.
     */
    public function extendIntoNextLevelIfExhausted(): void
    {
        // Groups now complete their currently assigned curriculum/level rather
        // than silently rolling into the next level.
    }

    public function getLevelBadgeAttribute(): string
    {
        return match($this->level) {
            'beginner'     => '<span class="badge badge-beginner">Beginner</span>',
            'intermediate' => '<span class="badge badge-intermediate">Intermediate</span>',
            'advanced'     => '<span class="badge badge-advanced">Advanced</span>',
            'expert'       => '<span class="badge badge-expert">Expert</span>',
            default        => ''
        };
    }
}
