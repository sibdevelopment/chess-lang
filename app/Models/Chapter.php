<?php namespace App\Models;
use App\Services\PgnChapterDocumentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Chapter extends Model
{
    use HasFactory;
    public const LEVELS = ['beginner', 'intermediate', 'advanced', 'expert'];
    protected $fillable = ['name','pgn','level','sequence_number','folder_id','uploaded_by','file_path','sort_order','description','move_count'];

    public function scopeOrderedForLevel($query)
    {
        return $query->orderByRaw('CASE WHEN sequence_number IS NULL THEN 1 ELSE 0 END')
            ->orderBy('sequence_number')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');
    }

    public function getLevelColorAttribute(): string {
        return match($this->level) {
            'beginner'=>'#059669',
            'intermediate'=>'#d97706',
            'advanced'=>'#dc2626',
            'expert'=>'#7c3aed',
            default=>'#64748b',
        };
    }

    public function folder()     { return $this->belongsTo(PgnFolder::class, 'folder_id'); }
    public function uploader()   { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function groups()     { return $this->belongsToMany(Group::class, 'chapter_group')->withPivot('sequence_order'); }
    public function homework()   { return $this->hasMany(Homework::class); }
    public function liveClasses(){ return $this->hasMany(LiveClass::class); }
    public function puzzleAnswers(){ return $this->hasMany(ChapterPuzzleAnswer::class); }

    public function effectivePgn(): string
    {
        $answers = $this->relationLoaded('puzzleAnswers')
            ? $this->puzzleAnswers
            : $this->puzzleAnswers()->orderBy('puzzle_index')->get();

        return app(PgnChapterDocumentService::class)->mergeSavedAnswers((string) $this->pgn, $answers);
    }

    public function getEffectivePgnAttribute(): string
    {
        return $this->effectivePgn();
    }

    public function getPuzzleCountAttribute(): int
    {
        return app(PgnChapterDocumentService::class)->countGames((string) $this->pgn);
    }

    public function parseMoveCount(): int
    {
        preg_match_all('/\d+\./', $this->pgn, $matches);
        return count($matches[0]);
    }
}
