<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DatabaseAssignment extends Model
{
    use HasFactory;

    protected $table = 'database_assignments';

    protected $fillable = [
        'folder_id',
        'coach_id',
        'assigned_by',
    ];

    public function folder()     { return $this->belongsTo(PgnFolder::class, 'folder_id'); }
    public function coach()      { return $this->belongsTo(User::class, 'coach_id'); }
    public function assignedBy() { return $this->belongsTo(User::class, 'assigned_by'); }
}
