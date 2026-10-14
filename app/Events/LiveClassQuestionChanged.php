<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveClassQuestionChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $roomId,
        public string $action,
        public ?int $questionId = null,
        public ?int $studentId = null,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("class.{$this->roomId}");
    }

    public function broadcastAs(): string
    {
        return 'question.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'question_id' => $this->questionId,
            'student_id' => $this->studentId,
        ];
    }
}
