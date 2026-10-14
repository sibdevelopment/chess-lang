<?php

namespace App\Http\Controllers\Live;

use App\Events\LiveClassChatMessageSent;
use App\Http\Controllers\Controller;
use App\Models\ClassChatMessage;
use App\Models\ClassParticipant;
use App\Models\LiveClass;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ChatController extends Controller
{
    /** GET — list messages since ?since=<id>, plus online count. Used by both roles. */
    public function list(Request $request, LiveClass $class, string $role)
    {
        $this->authorizeAccess($class, $role);
        $this->ensureChatTable();

        $since = (int) $request->query('since', 0);

        try {
            $messages = ClassChatMessage::where('live_class_id', $class->id)
                ->where('id', '>', $since)
                ->with('user:id,name,avatar_color')
                ->orderBy('id')
                ->limit(80)
                ->get()
                ->map(fn ($m) => [
                    'id'         => $m->id,
                    'user_id'    => $m->user_id,
                    'user_name'  => $m->user?->name ?? 'Unknown',
                    'role'       => $m->role,
                    'body'       => $m->body,
                    'created_at' => $m->created_at?->toIso8601String(),
                ]);
        } catch (Throwable $e) {
            Log::warning('Class chat list failed.', [
                'user_id' => Auth::id(),
                'action' => 'chat.list',
                'model_type' => LiveClass::class,
                'model_id' => $class->id,
                'route' => $request->route()?->getName(),
                'exception' => $e::class,
            ]);

            return response()->json([
                'messages' => [],
                'online'   => 0,
                'error'    => 'Chat is temporarily unavailable.',
            ], 500);
        }

        return response()->json([
            'messages' => $messages,
            'online'   => $this->liveStudentCount($class),
        ]);
    }

    /** POST — send a chat message. */
    public function send(Request $request, LiveClass $class, string $role)
    {
        $this->authorizeAccess($class, $role);
        $this->ensureChatTable();

        $data = $request->validate([
            'body' => 'required|string|max:500',
        ]);

        try {
            $msg = ClassChatMessage::create([
                'live_class_id' => $class->id,
                'user_id'       => Auth::id(),
                'role'          => $role,
                'body'          => trim($data['body']),
            ]);
        } catch (Throwable $e) {
            Log::warning('Class chat send failed.', [
                'user_id' => Auth::id(),
                'action' => 'chat.send',
                'model_type' => LiveClass::class,
                'model_id' => $class->id,
                'route' => $request->route()?->getName(),
                'exception' => $e::class,
            ]);

            return response()->json([
                'error' => 'Could not send the message right now.',
            ], 500);
        }

        $msg->load('user:id,name,avatar_color');

        $payload = [
            'id'         => $msg->id,
            'user_id'    => $msg->user_id,
            'user_name'  => $msg->user?->name ?? 'You',
            'role'       => $msg->role,
            'body'       => $msg->body,
            'created_at' => $msg->created_at?->toIso8601String(),
        ];

        try {
            broadcast(new LiveClassChatMessageSent($class->room_id, $payload));
        } catch (Throwable $e) {
            // The persisted message remains available through polling when the
            // local WebSocket server is temporarily unavailable.
        }

        return response()->json(['message' => $payload]);
    }

    /** Make sure the current user is allowed to read/post here. */
    protected function authorizeAccess(LiveClass $class, string $role): void
    {
        $user = Auth::user();
        abort_unless(in_array($role, ['coach', 'student'], true), 404);

        if ($role === 'coach') {
            abort_unless($class->coach_id === $user->id, 403);
        } else {
            // Use a fresh exists() query so we don't rely on the lazily-loaded
            // collection (which can be empty if the relationship isn't hydrated).
            $inGroup = $class->group
                && $class->group->students()->where('users.id', $user->id)->exists();
            abort_unless($inGroup, 403);
        }
    }

    /** Count students whose heartbeat is fresh in the last 30 seconds. */
    protected function liveStudentCount(LiveClass $class): int
    {
        try {
            return ClassParticipant::where('live_class_id', $class->id)
                ->where('last_seen_at', '>=', now()->subSeconds(30))
                ->count();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Self-healing: if migration 0006 wasn't applied (the missing-tables
     * fallback scenario this project explicitly ships an SQL fix for),
     * create the chat table on first use so the feature works immediately.
     */
    protected function ensureChatTable(): void
    {
        try {
            if (Schema::hasTable('class_chat_messages')) {
                return;
            }
            Schema::create('class_chat_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('live_class_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->enum('role', ['coach', 'student'])->index();
                $table->text('body');
                $table->timestamps();
                $table->index(['live_class_id', 'created_at']);
            });
        } catch (QueryException $e) {
            // Table likely exists already in a partial state — let the
            // upstream query surface the real error. We don't want to
            // crash if the schema isn't writable in this environment.
        } catch (Throwable $e) {
            // Swallow — we'll fail with a real error message in list()/send().
        }
    }
}
