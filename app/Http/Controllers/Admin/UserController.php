<?php namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use ZipArchive;

class UserController extends Controller
{
    protected const PEOPLE_PER_PAGE = 50;
    protected const STUDENT_USERNAME_PREFIX = 'ARCHERCHESS';
    protected const STUDENT_USERNAME_PAD_LENGTH = 2;
    protected const STUDENT_DEFAULT_PASSWORD = 'ARCHERCHESS';
    protected const STUDENT_USERNAME_NUMERIC_OFFSET = 12;
    protected const COACH_USERNAME_PREFIX = 'ARCHERCOACH';
    protected const STAFF_USERNAME_PREFIX = 'ARCHERSTAFF';
    protected const ROLE_USERNAME_PAD_LENGTH = 2;

    protected function userValidationRules(string $role, ?User $user = null): array
    {
        // Academy policy: name, username and password are collected for any role.
        // For students we also collect a short region code (USA, UK, EU, ME,
        // AUS, IND, CA, SG) for grouping & reports — academy is still IST-only,
        // so timezone is not asked.
        $rules = [
            'name'     => 'required|string|max:100',
            'username' => ['required', 'string', 'max:50', Rule::unique('users')->ignore($user?->id)],
            'is_active' => 'required|boolean',
        ];

        if ($role === 'student') {
            $allowed = array_keys(User::STUDENT_COUNTRY_CODES);
            // Allow blank, an allowed short code, or an existing legacy value (so
            // editing an old "India"-labelled record doesn't fail validation).
            $allowedExtra = $user && $user->country && !in_array($user->country, $allowed, true)
                ? [$user->country]
                : [];
            $rules['country'] = ['nullable', 'string', 'max:60', Rule::in(array_merge($allowed, $allowedExtra))];
        }

        if (in_array($role, ['coach', 'staff'], true)) {
            $rules['password'] = 'nullable|string';
        } elseif ($user) {
            $rules['password'] = 'nullable|string|min:6';
        } else {
            $rules['password'] = 'required|string|min:6';
        }

        return $rules;
    }

    protected function applyDerivedFields(array &$data, string $role): void
    {
        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = (bool) $data['is_active'];
        }

        // Normalise empty country strings to null for cleanliness.
        if ($role === 'student' && array_key_exists('country', $data) && $data['country'] === '') {
            $data['country'] = null;
        }
    }

    protected function userRoutePrefix(Request $request): string
    {
        return $request->user()?->isStaff() ? 'staff' : 'admin';
    }

    protected function studentStatusFilter(?string $status): string
    {
        return in_array($status, ['new_id', 'active', 'inactive'], true) ? $status : 'new_id';
    }

    protected function applyStudentStatusScope($query, string $status)
    {
        return match ($status) {
            'active' => $query
                ->whereNotNull('country')
                ->where('country', '!=', '')
                ->where('is_active', true),
            'inactive' => $query
                ->whereNotNull('country')
                ->where('country', '!=', '')
                ->where('is_active', false),
            default => $query
                ->where(function ($inner) {
                    $inner->whereNull('country')->orWhere('country', '');
                })
                ->where('is_active', false),
        };
    }

    protected function studentStatusCounts(): array
    {
        return [
            'new_id' => $this->applyStudentStatusScope(User::students(), 'new_id')->count(),
            'active' => $this->applyStudentStatusScope(User::students(), 'active')->count(),
            'inactive' => $this->applyStudentStatusScope(User::students(), 'inactive')->count(),
        ];
    }

    protected function nextRoleUsername(string $prefix): string
    {
        $highest = User::where('username', 'like', $prefix . '%')
            ->pluck('username')
            ->map(function ($username) use ($prefix) {
                $pattern = '/^' . preg_quote($prefix, '/') . '(\d+)$/i';

                return preg_match($pattern, $username, $matches) ? (int) $matches[1] : 0;
            })
            ->max() ?? 0;

        return $prefix . str_pad((string) ($highest + 1), self::ROLE_USERNAME_PAD_LENGTH, '0', STR_PAD_LEFT);
    }

    protected function generatedPasswordFromName(string $name): string
    {
        $firstName = trim((string) Str::of($name)->before(' '));
        $firstName = preg_replace('/[^A-Za-z0-9]/', '', $firstName ?? '') ?: 'User';

        return $firstName . '@1';
    }

    protected function normaliseStudentPayload(array &$data, ?User $user = null): void
    {
        $this->applyDerivedFields($data, 'student');

        $hasCountry = array_key_exists('country', $data) && $data['country'] !== null && $data['country'] !== '';

        if ($hasCountry) {
            $data['is_active'] = true;
            return;
        }

        if ($user && $user->country) {
            $data['is_active'] = (bool) ($data['is_active'] ?? $user->is_active);
            return;
        }

        $data['is_active'] = false;
    }

    protected function persistUser(User $user, array $data, string $successMessage, string $routeName)
    {
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        if (request()->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $successMessage]);
        }

        return redirect()->route($routeName)->with('success', $successMessage);
    }

    // ── Students ───────────────────────────────────────────────────────
    public function students(Request $request)
    {
        $search = $request->get('search');
        $students = User::students()
            ->where('is_active', true)
            ->when($search, fn($q) => $q->where(function ($qq) use ($search) {
                $qq->where('name','like',"%$search%")
                   ->orWhere('username','like',"%$search%");
            }))
            ->with('groups')
            ->orderByRaw('CAST(SUBSTRING(username, ' . self::STUDENT_USERNAME_NUMERIC_OFFSET . ') AS UNSIGNED)')
            ->paginate(self::PEOPLE_PER_PAGE)
            ->withQueryString();

        $counts = $this->studentStatusCounts();

        $assignableGroups = Group::with('coach:id,name')
            ->where('is_active', true)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'name', 'level', 'coach_id', 'is_active']);

        $nextUsername    = $this->nextStudentUsername();
        $defaultPassword = self::STUDENT_DEFAULT_PASSWORD;

        return view('admin.users.students', compact('students','search','counts','nextUsername','defaultPassword','assignableGroups'));
    }

    /**
     * Compute the next sequential ARCHERCHESS-style student username.
     * Scans all existing usernames matching ARCHERCHESS<digits> (case-insensitive),
     * finds the highest number, and returns the next one zero-padded to 2 digits.
     * Examples: ARCHERCHESS01, ARCHERCHESS02, … ARCHERCHESS99, ARCHERCHESS100.
     */
    protected function nextStudentUsername(): string
    {
        return $this->nextRoleUsername(self::STUDENT_USERNAME_PREFIX);
    }

    public function createStudent()
    {
        return redirect()->route($this->userRoutePrefix(request()).'.students');
    }

    public function storeStudent(Request $request)
    {
        $data = $request->validate($this->userValidationRules('student'));
        $this->normaliseStudentPayload($data);

        $data['role']     = 'student';
        $data['username'] = $this->nextStudentUsername();
        $data['password'] = Hash::make(self::STUDENT_DEFAULT_PASSWORD);

        $student = User::create($data);
        $routePrefix = $this->userRoutePrefix($request);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => "Student \"{$student->name}\" created successfully.",
            ]);
        }

        return redirect()->route($routePrefix.'.students')
            ->with('success', "Student \"{$student->name}\" created successfully.");
    }

    public function editStudent(User $user)
    {
        abort_unless($user->isStudent(), 403);
        return redirect()->route($this->userRoutePrefix(request()).'.students');
    }

    // ── Bulk import / export of students ───────────────────────────────
    /** Stream a CSV of every student. */
    public function exportStudents(Request $request)
    {
        $filename = 'students-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            // BOM so Excel opens UTF-8 correctly
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['name','username','country','status','groups','joined_at']);

            User::students()
                ->with('groups:id,name')
                ->orderByRaw('CAST(SUBSTRING(username, ' . self::STUDENT_USERNAME_NUMERIC_OFFSET . ') AS UNSIGNED)')
                ->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $s) {
                    fputcsv($out, [
                        $s->name,
                        $s->username,
                        $s->country ?? '',
                        $s->studentLifecycleStatus(),
                        $s->groups->pluck('name')->join('|'),
                        optional($s->created_at)->format('Y-m-d'),
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Bulk-create students from an uploaded CSV or Excel file. */
    public function importStudents(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt,xlsx|max:10240',
        ]);

        $uploadedFile = $request->file('csv_file');

        try {
            [$header, $rows] = $this->studentImportRows(
                $uploadedFile->getRealPath(),
                strtolower((string) $uploadedFile->getClientOriginalExtension())
            );
        } catch (RuntimeException $e) {
            return back()->with('error', 'The import file could not be read. Please check the format and try again.');
        }

        $header = array_map(fn($h) => strtolower(trim((string) $h)), $header);
        $idx = array_flip($header); // ['name'=>0,'username'=>1,...]

        if (!isset($idx['name'])) {
            return back()->with('error', 'Import file must contain at least a "name" column.');
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $groupCache = Group::pluck('id', 'name');
        $line = 1;

        foreach ($rows as $row) {
            $line++;
            if (count(array_filter($row, fn($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $name = trim((string) ($row[$idx['name']] ?? ''));
            $username = isset($idx['username']) ? trim((string) ($row[$idx['username']] ?? '')) : '';
            $password = isset($idx['password']) ? trim((string) ($row[$idx['password']] ?? '')) : '';
            $groupName = isset($idx['group']) ? trim((string) ($row[$idx['group']] ?? '')) : '';
            $groupsValue = isset($idx['groups']) ? trim((string) ($row[$idx['groups']] ?? '')) : '';
            $country  = isset($idx['country']) ? trim((string) ($row[$idx['country']] ?? '')) : '';
            $activeValue = isset($idx['status'])
                ? trim((string) ($row[$idx['status']] ?? ''))
                : (isset($idx['is_active']) ? trim((string) ($row[$idx['is_active']] ?? '')) : '');
            if ($country !== '' && !array_key_exists($country, User::STUDENT_COUNTRY_CODES)) {
                // Tolerate stray casing / whitespace before bailing out.
                $upper = strtoupper($country);
                $country = array_key_exists($upper, User::STUDENT_COUNTRY_CODES) ? $upper : '';
            }

            if ($name === '') {
                $errors[] = "Row {$line}: missing name — skipped.";
                $skipped++;
                continue;
            }

            if ($username === '') {
                $username = $this->nextStudentUsername();
            }
            $existingStudent = User::where('role', 'student')->where('username', $username)->first();
            $existingOtherRole = User::where('username', $username)
                ->when($existingStudent, fn ($query) => $query->whereKeyNot($existingStudent->id))
                ->exists();

            if ($existingOtherRole) {
                $errors[] = "Row {$line}: username \"{$username}\" already exists — skipped.";
                $skipped++;
                continue;
            }
            if ($password !== '' && strlen($password) < 6) {
                $errors[] = "Row {$line}: password too short (<6 chars) — skipped.";
                $skipped++;
                continue;
            }

            $status = $this->normaliseImportedStudentStatus($activeValue, $country);
            $groupNames = collect(explode('|', $groupsValue !== '' ? $groupsValue : $groupName))
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->values();

            $payload = [
                'name' => $name,
                'username' => $username,
                'role' => 'student',
                'country' => $country !== '' ? $country : null,
                'is_active' => $status,
            ];

            if ($password !== '') {
                $payload['password'] = Hash::make($password);
            }

            if ($existingStudent) {
                $existingStudent->update($payload);
                $student = $existingStudent->fresh();
                $updated++;
            } else {
                $payload['password'] = Hash::make($password !== '' ? $password : self::STUDENT_DEFAULT_PASSWORD);
                $student = User::create($payload);
                $created++;
            }

            if ($groupNames->isNotEmpty()) {
                $resolvedGroupIds = [];
                foreach ($groupNames as $groupLabel) {
                    if (isset($groupCache[$groupLabel])) {
                        $resolvedGroupIds[] = $groupCache[$groupLabel];
                    } else {
                        $errors[] = "Row {$line}: group \"{$groupLabel}\" not found — ignored.";
                    }
                }

                $student->groups()->sync(array_values(array_unique($resolvedGroupIds)));
            }
        }

        $msg = "Imported {$created} student(s), updated {$updated} student(s)" . ($skipped ? ", skipped {$skipped}" : '') . '.';
        return back()->with('success', $msg)->with('import_errors', $errors);
    }

    protected function normaliseImportedStudentStatus(string $value, string $country = ''): bool
    {
        $normalised = strtolower(trim($value));

        return match ($normalised) {
            '1', 'true', 'active' => true,
            '0', 'false', 'inactive', 'new_id', 'new id' => false,
            default => $country !== '',
        };
    }

    protected function studentImportRows(string $path, string $extension): array
    {
        return match ($extension) {
            'csv', 'txt' => $this->readStudentCsvRows($path),
            'xlsx' => $this->readStudentXlsxRows($path),
            default => throw new RuntimeException('Only CSV, TXT, and Excel (.xlsx) files are supported.'),
        };
    }

    protected function readStudentCsvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new RuntimeException('Could not open the uploaded file.');
        }

        $first = fread($handle, 3);
        if ($first !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            throw new RuntimeException('CSV is empty.');
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return [$header, $rows];
    }

    protected function readStudentXlsxRows(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Could not open the Excel file.');
        }

        $sharedStrings = $this->readXlsxSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

        if ($sheetXml === false) {
            $zip->close();
            throw new RuntimeException('The Excel file does not contain a readable first sheet.');
        }

        $rows = $this->parseXlsxSheetRows($sheetXml, $sharedStrings);
        $zip->close();

        if ($rows === []) {
            throw new RuntimeException('Excel file is empty.');
        }

        $header = array_shift($rows);

        return [$header, $rows];
    }

    protected function readXlsxSharedStrings(ZipArchive $zip): array
    {
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml === false) {
            return [];
        }

        $xml = simplexml_load_string($sharedXml);
        if (!$xml) {
            return [];
        }

        $strings = [];
        foreach ($xml->si as $item) {
            if (isset($item->t)) {
                $strings[] = (string) $item->t;
                continue;
            }

            $text = '';
            foreach ($item->r as $run) {
                $text .= (string) $run->t;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    protected function parseXlsxSheetRows(string $sheetXml, array $sharedStrings): array
    {
        $xml = simplexml_load_string($sheetXml);
        if (!$xml || !isset($xml->sheetData)) {
            return [];
        }

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $parsedRow = [];
            $expectedColumn = 0;

            foreach ($row->c as $cell) {
                $coordinate = (string) ($cell['r'] ?? '');
                $columnLetters = preg_replace('/\d+/', '', $coordinate);
                $columnIndex = $this->xlsxColumnIndex($columnLetters);

                while ($expectedColumn < $columnIndex) {
                    $parsedRow[] = '';
                    $expectedColumn++;
                }

                $parsedRow[] = $this->xlsxCellValue($cell, $sharedStrings);
                $expectedColumn++;
            }

            $rows[] = $parsedRow;
        }

        return $rows;
    }

    protected function xlsxColumnIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return max($index - 1, 0);
    }

    protected function xlsxCellValue(\SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) ($cell['t'] ?? '');

        if ($type === 'inlineStr') {
            return trim((string) ($cell->is->t ?? ''));
        }

        $value = isset($cell->v) ? (string) $cell->v : '';

        if ($type === 's') {
            return (string) ($sharedStrings[(int) $value] ?? '');
        }

        return trim($value);
    }

    public function updateStudent(Request $request, User $user)
    {
        abort_unless($user->isStudent(), 403);
        $data = $request->validate($this->userValidationRules('student', $user));
        $this->normaliseStudentPayload($data, $user);

        return $this->persistUser($user, $data, 'Student updated.', $this->userRoutePrefix($request).'.students');
    }

    public function assignStudentGroups(Request $request, User $user)
    {
        abort_unless($user->isStudent(), 403);

        $data = $request->validate([
            'group_ids' => 'nullable|array',
            'group_ids.*' => ['integer', Rule::exists('groups', 'id')],
        ]);

        $groupIds = collect($data['group_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $invalidGroupIds = Group::whereIn('id', $groupIds)
            ->where('is_active', false)
            ->pluck('id')
            ->all();

        if (!empty($invalidGroupIds)) {
            throw ValidationException::withMessages([
                'group_ids' => 'Only active groups can be assigned.',
            ]);
        }

        $user->groups()->sync($groupIds);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => 'Groups updated.']);
        }

        return back()->with('success', 'Groups updated.');
    }

    public function toggleActive(User $user)
    {
        if ($user->isStudent() && ($user->country === null || $user->country === '') && ! $user->is_active) {
            if (request()->expectsJson()) {
                return response()->json(['message' => 'New ID students must be completed with a country before activation.'], 422);
            }
            return back()->with('error', 'New ID students must be completed with a country before activation.');
        }

        $user->update(['is_active' => !$user->is_active]);
        $state = $user->is_active ? 'activated' : 'deactivated';

        if (request()->expectsJson()) {
            return response()->json(['ok' => true, 'message' => "{$user->name} has been {$state}."]);
        }

        return back()->with('success', "{$user->name} has been {$state}.");
    }

    // ── Staff ──────────────────────────────────────────────────────────
    public function staff(Request $request)
    {
        $search = $request->get('search');

        $staff = User::staff()
            ->whereKeyNot($request->user()->id)
            ->when($search, fn($q) => $q->where('name','like',"%$search%"))
            ->orderBy('name')->paginate(self::PEOPLE_PER_PAGE)->withQueryString();

        $counts = [
            'active'   => User::staff()->whereKeyNot($request->user()->id)->where('is_active', true)->count(),
            'inactive' => User::staff()->whereKeyNot($request->user()->id)->where('is_active', false)->count(),
        ];

        // Auto-suggest next staff username.
        $nextUsername = $this->nextRoleUsername(self::STAFF_USERNAME_PREFIX);
        $defaultPassword = old('name')
            ? $this->generatedPasswordFromName(old('name'))
            : '';

        return view('admin.users.staff', compact('staff','search','counts','nextUsername','defaultPassword'));
    }

    public function storeStaff(Request $request)
    {
        $data = $request->validate($this->userValidationRules('staff'));
        $this->applyDerivedFields($data, 'staff');
        $plainPassword = $this->generatedPasswordFromName($data['name']);
        $data['role']     = 'staff';
        $data['username'] = $this->nextRoleUsername(self::STAFF_USERNAME_PREFIX);
        $data['password'] = Hash::make($plainPassword);
        $user = User::create($data);
        $user->storeCredentialHint($plainPassword);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => "Staff \"{$user->name}\" created."]);
        }

        return redirect()->route('admin.staff')->with('success', "Staff \"{$user->name}\" created.");
    }

    public function editStaff(User $user)
    {
        abort_unless($user->isStaff(), 403);
        return redirect()->route('admin.staff');
    }

    public function updateStaff(Request $request, User $user)
    {
        abort_unless($user->isStaff(), 403);
        $data = $request->validate($this->userValidationRules('staff', $user));
        $this->applyDerivedFields($data, 'staff');
        $data['username'] = $user->username;
        unset($data['password']);

        return $this->persistUser($user, $data, 'Staff member updated.', 'admin.staff');
    }

    // ── Coaches ────────────────────────────────────────────────────────
    public function coaches(Request $request)
    {
        $search = $request->get('search');

        $coaches = User::coaches()
            ->when($search, fn($q) => $q->where('name','like',"%$search%"))
            ->with('coachedGroups:id,name,coach_id,level,is_active')
            ->withCount('coachedGroups')
            ->orderBy('name')->paginate(self::PEOPLE_PER_PAGE)->withQueryString();

        $counts = [
            'active'   => User::coaches()->where('is_active', true)->count(),
            'inactive' => User::coaches()->where('is_active', false)->count(),
        ];

        // Auto-suggest next coach username.
        $nextUsername = $this->nextRoleUsername(self::COACH_USERNAME_PREFIX);
        $defaultPassword = old('name')
            ? $this->generatedPasswordFromName(old('name'))
            : '';

        $assignableGroups = Group::with('coach:id,name')
            ->where('is_active', true)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'name', 'level', 'coach_id', 'is_active']);

        return view('admin.users.coaches', compact('coaches','search','counts','nextUsername','assignableGroups','defaultPassword'));
    }

    public function storeCoach(Request $request)
    {
        $data = $request->validate($this->userValidationRules('coach'));
        $this->applyDerivedFields($data, 'coach');
        $plainPassword = $this->generatedPasswordFromName($data['name']);
        $data['role']     = 'coach';
        $data['username'] = $this->nextRoleUsername(self::COACH_USERNAME_PREFIX);
        $data['password'] = Hash::make($plainPassword);
        $coach = User::create($data);
        $coach->storeCredentialHint($plainPassword);
        $routePrefix = $this->userRoutePrefix($request);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => "Coach \"{$coach->name}\" created."]);
        }

        return redirect()->route($routePrefix.'.coaches')->with('success', "Coach \"{$coach->name}\" created.");
    }

    public function editCoach(User $user)
    {
        abort_unless($user->isCoach(), 403);
        return redirect()->route($this->userRoutePrefix(request()).'.coaches');
    }

    public function updateCoach(Request $request, User $user)
    {
        abort_unless($user->isCoach(), 403);
        $data = $request->validate($this->userValidationRules('coach', $user));
        $this->applyDerivedFields($data, 'coach');
        $data['username'] = $user->username;
        unset($data['password']);

        return $this->persistUser($user, $data, 'Coach updated.', 'admin.coaches');
    }

    public function assignCoachGroups(Request $request, User $user)
    {
        abort_unless($user->isCoach(), 403);

        $data = $request->validate([
            'group_ids' => 'nullable|array',
            'group_ids.*' => ['integer', Rule::exists('groups', 'id')],
        ]);

        $groupIds = collect($data['group_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $invalidGroupIds = Group::whereIn('id', $groupIds)
            ->where('is_active', false)
            ->pluck('id')
            ->all();

        if (!empty($invalidGroupIds)) {
            throw ValidationException::withMessages([
                'group_ids' => 'Only active groups can be assigned.',
            ]);
        }

        Group::where('coach_id', $user->id)
            ->whereNotIn('id', $groupIds)
            ->update(['coach_id' => null]);

        if (!empty($groupIds)) {
            Group::whereIn('id', $groupIds)->update(['coach_id' => $user->id]);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => 'Groups updated.']);
        }

        return back()->with('success', 'Groups updated.');
    }
}
