<?php

namespace App\Listeners;

use App\Events\MatchFinished;
use App\Services\PayoutService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ProcessPayouts
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    protected $payoutService;
    public function __construct(PayoutService $payoutService)
    {
        //
        $this->payoutService = $payoutService;
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(MatchFinished $event)
    {
        //
        $match = $event->match;
        $this->payoutService->processPayoutsForMatch($match);
    }
}
