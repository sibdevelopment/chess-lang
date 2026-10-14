<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

// ══════════════════════════════════════════
// PgnFolder
// ══════════════════════════════════════════
class PgnFolder extends Model
{
    use HasFactory;
    protected $table = 'pgn_folders';
    protected $fillable = ['name','level','parent_id','created_by','description','sort_order'];

    public function parent()   { return $this->belongsTo(PgnFolder::class, 'parent_id'); }
    public function children() { return $this->hasMany(PgnFolder::class, 'parent_id')->orderBy('sort_order'); }
    public function chapters() { return $this->hasMany(Chapter::class, 'folder_id')->orderBy('sort_order'); }
    public function creator()  { return $this->belongsTo(User::class, 'created_by'); }
    public function assignments() { return $this->hasMany(DatabaseAssignment::class, 'folder_id'); }

    public function getLevelColorAttribute(): string {
        return match($this->level) { 'beginner'=>'#059669','intermediate'=>'#d97706','advanced'=>'#dc2626', default=>'#64748b' };
    }
}
