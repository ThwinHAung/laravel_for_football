<?php

namespace App\Listeners;

use App\Events\MatchPostponed;
use App\Services\postponeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class postpone
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    protected $postponeService;
    public function __construct(PostponeService $postponeService)
    {
        //
        $this->postponeService = $postponeService;
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\MatchPostponed  $event
     * @return void
     */
    public function handle(MatchPostponed $event)
    {
        //
        $match = $event->match;
        $this->postponeService->payoutPostpone($match);
    }
}
