<?php
namespace App\Services;

use App\Models\Accumulator;
use App\Models\Matches;
use App\Models\Bets;
use App\Models\Commissions;
use App\Models\MixBetCommissions;
use App\Models\Report;
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
        $commission_id = Commissions::create([
            'bet_id' => $bet->id
        ])->id;
        Report::create([
            'user_id'=>$bet->user_id,
            'bet_id'=>$bet->id,
            'commissions_id'=>$commission_id,
            'turnover'=>$bet->amount,
            'type'=> 'Refund'
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
        $activeMatchesCount = Accumulator::where('bet_id', $betId)
            ->whereHas('match', function($query) {
                $query->where('IsEnd', false)
                      ->where('IsPost', false);
            })
            ->count();
    
        if ($activeMatchesCount > 0) {
            return false;
        }
        $allPostponed = Accumulator::where('bet_id', $betId)
        ->whereHas('match', function ($query) {
            $query->where('IsPost', true);
        })
        ->count();
        $totalMatchesCount = Accumulator::where('bet_id', $betId)->count();

        if ($allPostponed == $totalMatchesCount) {
            $this->refundAccumulators($betId);
            return false;
        }
        $nonPostponedEndedMatchesCount = Accumulator::where('bet_id', $betId)
        ->whereHas('match', function ($query) {
            $query->where('IsEnd', true)
                  ->where('IsPost', false);
        })
        ->count();

        if ($nonPostponedEndedMatchesCount > 0) {
            return false;
        }
        return true;
    }

    protected function refundAccumulators($betId){
        $bet = Bets::where('id', $betId)->first();
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
        $commission_id = Commissions::create([
            'bet_id' => $bet->id
        ])->id;
        Report::create([
            'user_id'=>$bet->user_id,
            'bet_id'=>$bet->id,
            'commissions_id'=>$commission_id,
            'turnover'=>$bet->amount,
            'type'=> 'Refund'
        ]);
    }

    protected function processAccumulatorPayout($betId) {
        DB::transaction(function () use ($betId) {
            $bet = Bets::where('id', $betId)->lockForUpdate()->first();
            if ($bet) {
                $accumulatorBets = Accumulator::where('bet_id', $betId)->lockForUpdate()->get();
                $matchCount = $accumulatorBets->count();
                $commission_id = $this->calculateAccumulatorBetCommission($bet,$bet->user_id,$bet->amount,$matchCount);

                if ($accumulatorBets->where('status', 'Lose')->count() > 0) {
                    $bet->status = 'Lose';
                    $bet->wining_amount = 0;
                    $bet->save();
                    Report::create([
                        'user_id'=>$bet->user_id,
                        'bet_id'=>$bet->id,
                        'commissions_id'=>$commission_id,
                        'turnover'=>$bet->amount,
                        'valid_amount'=> $bet->amount,
                        'win_loss'=> $bet->amount,
                        'type'=> 'Win'
        ,            ]);
                } else {
                    $totalOdds = $accumulatorBets->reduce(function ($carry, $item) {
                        return $carry * $item->wining_odd;
                    }, 1.0);

                    $winningAmount = $bet->amount * $totalOdds;
                    $taxRate = $this->getAccumulatorTaxRate($matchCount);
                    $taxAmount = $winningAmount * $taxRate;
                    $netWinnings = $winningAmount - $taxAmount;

                    $bet->wining_amount = $netWinnings;
                    $bet->status = 'Win';
                    $bet->save();
                    Report::create([
                        'user_id'=>$bet->user_id,
                        'bet_id'=>$bet->id,
                        'commissions_id'=>$commission_id,
                        'turnover'=>$bet->amount,
                        'valid_amount'=>$bet->amount,
                        'win_loss'=> $netWinnings,
                        'type'=> 'Los'
        ,            ]);
                }
                $this->updateUserBalance($bet->user_id, $bet->wining_amount);
                $user = User::find($bet->user_id);
                Transition::create([
                    'user_id' => $bet->user_id,
                    'description' => 'Win (Bet ID: ' . $bet->id . ')',
                    'type' => 'IN',
                    'amount' => $bet->wining_amount,
                    'WIN'=>$bet->wining_amount,
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

    protected function calculateAccumulatorBetCommission(Bets $bet,$userId, $betAmount, $matchCount)
    {
        $user = User::find($userId);
        $currentRole = $user->role;
        $currentCommission = MixBetCommissions::where('user_id', $user->id)->first();
        $commissionType = 'm' . $matchCount;
    
        $commissionGiven = 0; 
        $commissionData = [
            'bet_id' => $bet->id,
            'user' => 0,
            'agent' => 0,
            'master' => 0,
            'senior' => 0,
            'ssenior' => 0
        ];
    
        while ($user && $currentRole && $currentRole->name !== 'SSSenior') {
            $commissionPercentage = $currentCommission ? $currentCommission->{$commissionType} : 0;
            
            if ($commissionPercentage == 0) {
                $parentUser = User::find($user->created_by); 
                if ($parentUser) {
                    $user = $parentUser;
                    $currentRole = $user->role;
                    $currentCommission = MixBetCommissions::where('user_id', $user->id)->first();
                } else {
                    break;
                }
                continue;
            }
            $netCommission = $commissionPercentage - $commissionGiven;
            if ($netCommission <= 0) {
                break;
            }
    
            $commissionAmount = $betAmount * ($netCommission / 100);
    
            $this->updateUserBalance($user->id, $commissionAmount);
    
            switch ($currentRole->name) {
                case 'User':
                    $commissionData['user'] = $commissionAmount;
                    break;
                case 'Agent':
                    $commissionData['agent'] = $commissionAmount;
                    break;
                case 'Master':
                    $commissionData['master'] = $commissionAmount;
                    break;
                case 'Senior':
                    $commissionData['senior'] = $commissionAmount;
                    break;
                case 'SSenior':
                    $commissionData['ssenior'] = $commissionAmount;
                    break;
            }
    
            Transition::create([
                'user_id' => $user->id,
                'description' => 'Accumulator Commission (Bet ID: ' . $bet->id . ')',
                'type' => 'IN',
                'amount' => $commissionAmount,
                'commission'=> $commissionAmount,
                'balance' => $user->balance
            ]);
    
            $commissionGiven += $netCommission;
    
            $parentUser = User::find($user->created_by);
            if ($parentUser) {
                $user = $parentUser;
                $currentRole = $user->role;
                $currentCommission = MixBetCommissions::where('user_id', $user->id)->first();
            } else {
                break;
            }
        }
        $commissionRecord = Commissions::create($commissionData);
    
        return $commissionRecord->id;
        }
}
