<?php

namespace App\Jobs;

use App\Services\ArcherJourney\ArcherJourneyProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessArcherJourneyEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [10, 30, 120, 300];

    public function __construct(public int $activityEventId)
    {
        $this->onQueue('archer-journey');
    }

    public function handle(ArcherJourneyProcessor $processor): void
    {
        $processor->process($this->activityEventId);
    }
}
