<?php
// app/Events/BoardMoved.php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BoardMoved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $roomId,
        public int    $moveIndex,
        public string $boardState,
        public string $move,
        public string $arrows = '',
        public string $highlights = ''
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("class.{$this->roomId}");
    }

    public function broadcastAs(): string { return 'board.moved'; }

    public function broadcastWith(): array
    {
        return [
            'move_index'  => $this->moveIndex,
            'board_state' => $this->boardState,
            'move'        => $this->move,
            'arrows'      => $this->arrows,
            'highlights'  => $this->highlights,
        ];
    }
}
