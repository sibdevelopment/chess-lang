#!/usr/bin/env python3
import os, subprocess

print("Fixing Chess Academy...")

# ── Fix AuthController ────────────────────────────────────────────
auth = '''<?php
namespace App\\Http\\Controllers;

use App\\Models\\User;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Auth;
use Illuminate\\Support\\Facades\\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route(Auth::user()->role . '.dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', trim($request->username))
                    ->where('is_active', true)
                    ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return back()
                ->withErrors(['username' => 'Invalid username or password.'])
                ->withInput($request->except('password'));
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->update(['last_seen' => now()]);

        return redirect()->intended(route($user->role . '.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
'''

# ── Fix User Model ────────────────────────────────────────────────
user = '''<?php
namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;
use Illuminate\\Foundation\\Auth\\User as Authenticatable;
use Illuminate\\Notifications\\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'username', 'email', 'password', 'role',
        'avatar', 'phone', 'is_active', 'last_seen',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen' => 'datetime',
    ];

    public function scopeActive($q)   { return $q->where('is_active', true); }
    public function scopeStudents($q) { return $q->where('role', 'student'); }
    public function scopeCoaches($q)  { return $q->where('role', 'coach'); }
    public function scopeStaff($q)    { return $q->where('role', 'staff'); }

    public function isAdmin()   { return $this->role === 'admin'; }
    public function isStaff()   { return $this->role === 'staff'; }
    public function isCoach()   { return $this->role === 'coach'; }
    public function isStudent() { return $this->role === 'student'; }

    public function groups() {
        return $this->belongsToMany(Group::class, 'group_student', 'user_id', 'group_id')->withTimestamps();
    }
    public function coachedGroups()       { return $this->hasMany(Group::class, 'coach_id'); }
    public function liveClasses()         { return $this->hasMany(LiveClass::class, 'coach_id'); }
    public function homeworkSubmissions() { return $this->hasMany(HomeworkSubmission::class); }
    public function databaseAssignments() { return $this->hasMany(DatabaseAssignment::class, 'coach_id'); }

    public function getInitialsAttribute(): string {
        $words = array_filter(explode(' ', $this->name));
        return strtoupper(implode('', array_map(fn($w) => $w[0], $words)));
    }

    public function getAvatarColorAttribute(): string {
        $colors = ['#4f46e5','#0891b2','#059669','#d97706','#dc2626','#7c3aed','#db2777'];
        return $colors[$this->id % count($colors)];
    }
}
'''

# Write files
os.makedirs('app/Http/Controllers', exist_ok=True)
os.makedirs('app/Models', exist_ok=True)

with open('app/Http/Controllers/AuthController.php', 'w') as f:
    f.write(auth)
print("✓ AuthController.php written")

with open('app/Models/User.php', 'w') as f:
    f.write(user)
print("✓ User.php written")

# Run artisan commands
cmds = [
    ['php', 'artisan', 'session:table'],
    ['php', 'artisan', 'migrate', '--force'],
    ['php', 'artisan', 'config:clear'],
    ['php', 'artisan', 'cache:clear'],
    ['php', 'artisan', 'view:clear'],
    ['php', 'artisan', 'route:clear'],
]

for cmd in cmds:
    result = subprocess.run(cmd, capture_output=True, text=True)
    print(f"✓ {' '.join(cmd[2:])}")

# Re-seed
print("Clearing and re-seeding users...")
tinker_code = """
DB::statement('SET FOREIGN_KEY_CHECKS=0;');
DB::table('homework_submissions')->truncate();
DB::table('homework')->truncate();
DB::table('class_participants')->truncate();
DB::table('live_classes')->truncate();
DB::table('chapter_group')->truncate();
DB::table('chapters')->truncate();
DB::table('pgn_folders')->truncate();
DB::table('database_assignments')->truncate();
DB::table('group_student')->truncate();
DB::table('groups')->truncate();
DB::table('users')->truncate();
DB::statement('SET FOREIGN_KEY_CHECKS=1;');
"""
with open('/tmp/chess_tinker.php', 'w') as f:
    f.write(tinker_code)

subprocess.run(['php', 'artisan', 'tinker', '--execute', tinker_code], capture_output=True)
print("✓ Tables cleared")

result = subprocess.run(['php', 'artisan', 'db:seed', '--force'], capture_output=True, text=True)
if result.returncode == 0:
    print("✓ Demo users seeded")
else:
    print("Seed output:", result.stdout, result.stderr)

print("""
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  ✅  Done! Now run:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  php artisan serve

  Login at http://localhost:8000
  admin    / admin123
  coach1   / coach123
  student1 / stu123
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
""")
