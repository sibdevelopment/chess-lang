<?php namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChapterPuzzleAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'chapter_id',
        'puzzle_index',
        'original_checksum',
        'answer_pgn',
        'final_fen',
        'saved_by',
    ];

    public function chapter()
    {
        return $this->belongsTo(Chapter::class);
    }

    public function saver()
    {
        return $this->belongsTo(User::class, 'saved_by');
    }
}
