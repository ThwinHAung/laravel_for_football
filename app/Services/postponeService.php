<?php
namespace App\Services;

use App\Models\Accumulator;
use App\Models\Matches;
use App\Models\Bets;
use App\Models\Transition;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PostponeService {
    public function payoutPostpone(Matches $match) {
        $singleBets = Bets::where('match_id', $match->id)
            ->where('bet_type', 'single')
            ->where('status', 'Accepted')
            ->get();

        foreach ($singleBets as $singleBet) {
            $this->calculateSingleBetPostpone($singleBet);
        }

        DB::transaction(function () use ($match) {
            $accumulators = Accumulator::where('match_id', $match->id)
                ->where('status', 'Accepted')
                ->lockForUpdate()
                ->get();

            foreach ($accumulators as $accumulator) {
                $this->calculateAccumulatorPostpone($accumulator);

                if ($this->allMatchesCompleted($accumulator->bet_id)) {
                    $this->processAccumulatorPayout($accumulator->bet_id);
                }
            }
        });
    }

    public function calculateSingleBetPostpone(Bets $bet) {
        $bet->status = 'Refund';
        $bet->wining_amount = $bet->amount; 
        $this->updateUserBalanceForPP($bet->user_id, $bet->amount);
        $bet->save();

        $user = User::find($bet->user_id);
        Transition::create([
            'user_id' => $bet->user_id,
            'description' => 'Refund (Bet ID: ' . $bet->id . ')',
            'type' => 'IN',
            'amount' => $bet->amount,
            'IN'=>$bet->amount,
            'balance' => $user->balance
        ]);
    }

    protected function calculateAccumulatorPostpone(Accumulator $accumulator) {
        $accumulator->wining_odd = 1.0;
        $accumulator->status = 'Refund';
        $accumulator->save();
    }

    protected function updateUserBalanceForPP($userId, $amount) {
        $user = User::find($userId);
        if ($user) {
            $user->balance += $amount;
            $user->save();
        }
    }

    protected function allMatchesCompleted($betId) {
        $count = Accumulator::where('bet_id', $betId)
            ->whereHas('match', function($query) {
                $query->where('IsEnd', false)
                      ->where('IsPost', false);
            })
            ->count();
    
        return $count == 0;
    }

    protected function processAccumulatorPayout($betId) {
        DB::transaction(function () use ($betId) {
            $bet = Bets::where('id', $betId)->lockForUpdate()->first();
            if ($bet) {
                $accumulatorBets = Accumulator::where('bet_id', $betId)->lockForUpdate()->get();

                if ($accumulatorBets->where('status', 'Lose')->count() > 0) {
                    $bet->status = 'Lose';
                    $bet->wining_amount = 0;
                } else {
                    $totalOdds = $accumulatorBets->reduce(function ($carry, $item) {
                        return $carry * $item->wining_odd;
                    }, 1.0);

                    $winningAmount = $bet->amount * $totalOdds;
                    $matchCount = $accumulatorBets->count();
                    $taxRate = $this->getAccumulatorTaxRate($matchCount);
                    $taxAmount = $winningAmount * $taxRate;
                    $netWinnings = $winningAmount - $taxAmount;

                    $bet->wining_amount = $netWinnings;
                    $bet->status = 'Win';
                }

                $bet->save();
                $this->updateUserBalance($bet->user_id, $bet->wining_amount);
                $user = User::find($bet->user_id);
                Transition::create([
                    'user_id' => $bet->user_id,
                    'description' => 'Win (Bet ID: ' . $bet->id . ')',
                    'type' => 'IN',
                    'amount' => $bet->wining_amount,
                    'balance' => $user->balance
                ]);
            }
        });
    }
    protected function updateUserBalance($userId, $amount)
    {
        $user = User::find($userId);
        if ($user) {
            $newBalance = $user->balance + $amount;
            $user->balance = $newBalance;
            $user->save();
        }
    }

    protected function getAccumulatorTaxRate($matchCount) {
        if ($matchCount < 3) {
            return 0.15;
        } elseif ($matchCount <= 11) {
            return 0.20; 
        } else {
            return 0.0; 
        }
    }
}
