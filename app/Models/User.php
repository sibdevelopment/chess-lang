<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'username', 'email', 'password', 'role',
        'avatar', 'phone', 'country', 'timezone', 'is_active', 'last_seen',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen' => 'datetime',
    ];

    /**
     * Short region codes used for the student profile dropdown.
     * Order is the academy's canonical region order.
     */
    public const STUDENT_COUNTRY_CODES = [
        'IND' => '🇮🇳 India',
        'USA' => '🇺🇸 USA',
        'CA' => '🇨🇦 Canada',
        'UK' => '🇬🇧 United Kingdom',
        'EU' => '🇪🇺 Europe',
        'ME' => '🌍 Middle East',
        'SG' => '🇸🇬 Singapore',
        'AUS' => '🇦🇺 Australia',
        'NZ' => '🇳🇿 New Zealand',
        'ZA' => '🇿🇦 South Africa',
    ];

    public static function studentCountryCodes(): array
    {
        return self::STUDENT_COUNTRY_CODES;
    }

    /**
     * Pretty label for a saved short code; falls back to the raw value for
     * legacy long-form entries (e.g. 'India', 'United Kingdom').
     */
    public static function studentCountryLabel(?string $code): string
    {
        if ($code === null || $code === '') {
            return '—';
        }

        return self::STUDENT_COUNTRY_CODES[$code] ?? $code;
    }

    public function studentLifecycleStatus(): string
    {
        if (! $this->isStudent()) {
            return $this->is_active ? 'active' : 'inactive';
        }

        if ($this->country === null || $this->country === '') {
            return 'new_id';
        }

        return $this->is_active ? 'active' : 'inactive';
    }

    public static function countryOptions(): array
    {
        return [
            'India' => 'Asia/Kolkata',
            'United Arab Emirates' => 'Asia/Dubai',
            'Saudi Arabia' => 'Asia/Riyadh',
            'Qatar' => 'Asia/Qatar',
            'Singapore' => 'Asia/Singapore',
            'Malaysia' => 'Asia/Kuala_Lumpur',
            'Thailand' => 'Asia/Bangkok',
            'Indonesia (Jakarta)' => 'Asia/Jakarta',
            'Philippines' => 'Asia/Manila',
            'China' => 'Asia/Shanghai',
            'Japan' => 'Asia/Tokyo',
            'South Korea' => 'Asia/Seoul',
            'Australia (Sydney)' => 'Australia/Sydney',
            'New Zealand' => 'Pacific/Auckland',
            'United Kingdom' => 'Europe/London',
            'Germany' => 'Europe/Berlin',
            'France' => 'Europe/Paris',
            'Spain' => 'Europe/Madrid',
            'South Africa' => 'Africa/Johannesburg',
            'Nigeria' => 'Africa/Lagos',
            'Canada (Toronto)' => 'America/Toronto',
            'United States (New York)' => 'America/New_York',
            'United States (Chicago)' => 'America/Chicago',
            'United States (Denver)' => 'America/Denver',
            'United States (Los Angeles)' => 'America/Los_Angeles',
            'Mexico' => 'America/Mexico_City',
            'Brazil' => 'America/Sao_Paulo',
        ];
    }

    public function preferredTimezone(): string
    {
        $timezone = trim((string) $this->timezone);

        if ($timezone !== '' && in_array($timezone, timezone_identifiers_list(), true)) {
            return $timezone;
        }

        return config('app.timezone', 'UTC');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeStudents($q)
    {
        return $q->where('role', 'student');
    }

    public function scopeCoaches($q)
    {
        return $q->where('role', 'coach');
    }

    public function scopeStaff($q)
    {
        return $q->where('role', 'staff');
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isStaff()
    {
        return $this->role === 'staff';
    }

    public function isCoach()
    {
        return $this->role === 'coach';
    }

    public function isStudent()
    {
        return $this->role === 'student';
    }

    public function landingPath(): string
    {
        return match ($this->role) {
            'admin' => '/admin/dashboard',
            'staff' => '/staff/dashboard',
            'coach' => '/coach/classes',
            'student' => '/student/classes',
            default => '/login',
        };
    }

    public function landingRouteName(): ?string
    {
        return match ($this->role) {
            'admin' => 'admin.dashboard',
            'staff' => 'staff.dashboard',
            'coach' => 'coach.classes',
            'student' => 'student.classes',
            default => null,
        };
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_student', 'user_id', 'group_id')->withTimestamps();
    }

    public function coachedGroups()
    {
        return $this->hasMany(Group::class, 'coach_id');
    }

    public function liveClasses()
    {
        return $this->hasMany(LiveClass::class, 'coach_id');
    }

    public function homeworkSubmissions()
    {
        return $this->hasMany(HomeworkSubmission::class);
    }

    public function databaseAssignments()
    {
        return $this->hasMany(DatabaseAssignment::class, 'coach_id');
    }

    public function performanceAssessments()
    {
        return $this->hasMany(StudentPerformanceAssessment::class, 'student_id');
    }

    public function coachSavedPerformanceAssessments()
    {
        return $this->hasMany(StudentPerformanceAssessment::class, 'student_id')->savedByCoach();
    }

    public function publishedPerformanceAssessments()
    {
        return $this->hasMany(StudentPerformanceAssessment::class, 'student_id')->published();
    }

    public function givenPerformanceAssessments()
    {
        return $this->hasMany(StudentPerformanceAssessment::class, 'assessor_id');
    }

    public function archerActivityEvents()
    {
        return $this->hasMany(ArcherActivityEvent::class);
    }

    public function archerStarTransactions()
    {
        return $this->hasMany(ArcherStarTransaction::class);
    }

    public function archerStarBalance()
    {
        return $this->hasOne(ArcherStarBalance::class);
    }

    public function chessRatings()
    {
        return $this->hasMany(StudentChessRating::class);
    }

    public function archerBadgeAwards()
    {
        return $this->hasMany(ArcherBadgeAward::class);
    }

    public function archerMissionProgress()
    {
        return $this->hasMany(ArcherMissionProgress::class);
    }

    public function archerNotifications()
    {
        return $this->hasMany(ArcherNotification::class);
    }

    public function archerPassportEntries()
    {
        return $this->hasMany(ArcherPassportEntry::class);
    }

    public function getInitialsAttribute(): string
    {
        $words = array_filter(explode(' ', $this->name));

        return strtoupper(implode('', array_map(fn ($w) => $w[0], $words)));
    }

    public function getAvatarColorAttribute(): string
    {
        $colors = ['#4f46e5', '#0891b2', '#059669', '#d97706', '#dc2626', '#7c3aed', '#db2777'];

        return $colors[$this->id % count($colors)];
    }

    public function credentialHintCacheKey(): string
    {
        return 'user_password_hint:'.$this->id;
    }

    public function storeCredentialHint(?string $password): void
    {
        if ($password === null || $password === '') {
            return;
        }

        Cache::forever($this->credentialHintCacheKey(), Crypt::encryptString($password));
    }

    public function credentialHint(): ?string
    {
        $encrypted = Cache::get($this->credentialHintCacheKey());

        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }
}
