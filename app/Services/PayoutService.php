<?php

namespace App\Services;

use App\Models\Accumulator;
use App\Models\Matches;
use App\Models\Bets;
use App\Models\MixBetCommissions;
use App\Models\SingleCommissions;
use App\Models\Transition;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpParser\Node\Stmt\Switch_;

class PayoutService
{
    public function processPayoutsForMatch(Matches $match)
    {
        $singleBets = Bets::where('match_id', $match->id)
            ->where('bet_type', 'single')
            ->where('status', 'Accepted')
            ->get();

        foreach($singleBets as $singleBet) {
            $this->calculateSingleBetPayout($singleBet, $match);
        }

        DB::transaction(function () use ($match) {
            $accumulators = Accumulator::where('match_id', $match->id)->where('status', 'Accepted')->lockForUpdate()->get(); 
    
            foreach ($accumulators as $accumulator) {
                $this->calculateAccumulatorPayout($accumulator, $match);
    
                if ($this->allMatchesCompleted($accumulator->bet_id)) {
                    $this->processAccumulatorPayout($accumulator->bet_id);
                }
            }
        });

    }

    protected function calculateSingleBetPayout(Bets $bet, Matches $match)
    {

        $potentialWinningAmount = $this->calculatePotentialWinningAmount($bet, $match);

        if ($potentialWinningAmount > $bet->amount) {
            $winningAmount = $potentialWinningAmount - $bet->amount;
            $taxRate = $this->getTaxRate($match->League);
            $taxAmount = $winningAmount * $taxRate;
            $netWinnings = $winningAmount - $taxAmount;
            
            $bet->status = 'Win';
            $bet->wining_amount = $netWinnings + $bet->amount; 
            $this->updateUserBalance($bet->user_id, $netWinnings + $bet->amount);
            $bet->save();
            $user = User::find($bet->user_id);
            Transition::create([
                'user_id' => $bet->user_id,
                'description' => 'Win (Bet ID: ' . $bet->id . ')',
                'type' => 'IN',
                'amount'=>$bet->wining_amount,
                'Win'=>$bet->wining_amount,
                'balance'=>$user->balance
            ]);
        } else if($potentialWinningAmount > 0){
            $bet->status = 'Win';
            $bet->wining_amount = $potentialWinningAmount;
            $this->updateUserBalance($bet->user_id, $bet->wining_amount);
            $bet->save();
            $user = User::find($bet->user_id);
            Transition::create([
                'user_id' => $bet->user_id,
                'description' => 'Win (Bet ID: ' . $bet->id . ')',
                'type' => 'IN',
                'amount'=>$bet->wining_amount,
                'Win'=>$bet->wining_amount,
                'balance'=>$user->balance
            ]);
        }else{
            $bet->status = 'Lose';
            $bet->save();
        }
        $this->calculateSingleBetCommission($bet->user_id, $bet->amount, $match->league);
    }

    protected function calculatePotentialWinningAmount(Bets $bet,Matches $match){
        $amount = $bet->amount;
        $homeGoals = $match->HomeGoal;
        $awayGoals = $match->AwayGoal;
        $totalGoals = $homeGoals + $awayGoals;
        $HomeUp = $match->HomeUp;
        $HdpGoal = $match->HdpGoal;
        $HdpUnit = $match->HdpUnit;
        $GpGoal = $match->GpGoal;
        $GpUnit = $match->GpUnit;

        switch($bet->selected_outcome){
            case 'W1':
                return $this->calculateW1Payout($amount, $homeGoals, $awayGoals, $HomeUp, $HdpGoal, $HdpUnit);
            case 'W2':
                return $this->calculateW2Payout($amount, $homeGoals, $awayGoals, $HomeUp, $HdpGoal, $HdpUnit);
            case 'Over':
                if($totalGoals > $GpGoal){
                    return $amount * 2;
                }elseif($totalGoals == $GpGoal){
                    return $amount * (1 + ($GpUnit / 100));
                }else{
                    return 0;
                }
            case 'Under':
                if($totalGoals < $GpGoal){
                    return $amount * 2;
                }elseif($totalGoals == $GpGoal){
                    return $amount * (1 + ($GpUnit / 100));
                }else{
                    return 0;
                }
        }
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

    protected function calculateW1Payout($amount, $homeGoals, $awayGoals, $HomeUp, $HdpGoal, $HdpUnit) {
        $goalDifference = $homeGoals - $awayGoals;
    
        if ($HomeUp == true) {
            if ($goalDifference > $HdpGoal) {
                return $amount * 2; 
            } elseif ($goalDifference == $HdpGoal) {
                return $amount * (1 + ($HdpUnit / 100)); 
            } elseif ($goalDifference == 0 && $HdpGoal == 0) {
                return $amount * (1 + ($HdpUnit / 100)); 
            } else {
                return 0; 
            }
        } elseif ($HomeUp == false) {
            $adjustedGoalDifference = -$goalDifference;
    
            if ($adjustedGoalDifference > $HdpGoal) {
                return 0; 
            } elseif ($adjustedGoalDifference == $HdpGoal) {
                return $amount * (1 + ($HdpUnit / 100)); 
            } elseif ($adjustedGoalDifference == 0 && $HdpGoal == 0) {
                return $amount * (1 + ($HdpUnit / 100));
            } else {
                return $amount * 2; 
            }
        }
    }
    

    protected function calculateW2Payout($amount, $homeGoals, $awayGoals, $HomeUp, $HdpGoal, $HdpUnit) {
        $goalDifference = $awayGoals - $homeGoals;
    
        if ($HomeUp == false) {
            if ($goalDifference > $HdpGoal) {
                return $amount * 2; 
            } elseif ($goalDifference == $HdpGoal) {
                return $amount * (1 + ($HdpUnit / 100)); 
            } elseif ($goalDifference == 0 && $HdpGoal == 0) {
                return $amount * (1 + ($HdpUnit / 100)); 
            } else {
                return 0; 
            }
        } elseif ($HomeUp == true) {
            $adjustedGoalDifference = -$goalDifference;
    
            if ($adjustedGoalDifference > $HdpGoal) {
                return 0; 
            } elseif ($adjustedGoalDifference == $HdpGoal) {
                return $amount * (1 + ($HdpUnit / 100)); 
            } elseif ($adjustedGoalDifference == 0 && $HdpGoal == 0) {
                return $amount * (1 + ($HdpUnit / 100)); 
            } else {
                return $amount * 2; 
            }
        }
    }

    protected function calculateAccumulatorPayout(Accumulator $accumulator,Matches $match){
        $potentialWiningOdd = $this->calculatePotentialWinningOdd($accumulator,$match);
        
        if($potentialWiningOdd > 0){
            $accumulator->status = 'Win';
        }else{
            $accumulator->status = 'Lose';
        }
        $accumulator->wining_odd = $potentialWiningOdd;
        $accumulator->save();
    }
    
    protected function calculatePotentialWinningOdd(Accumulator $accumulator,Matches $match){
        $homeGoals = $match->HomeGoal;
        $awayGoals = $match->AwayGoal;
        $totalGoals = $homeGoals + $awayGoals;
        $HomeUp = $match->HomeUp;
        $HdpGoal = $match->HdpGoal;
        $HdpUnit = $match->HdpUnit;
        $GpGoal = $match->GpGoal;
        $GpUnit = $match->GpUnit;

        switch($accumulator->selected_outcome){
            case 'W1':
                return $this->calculateW1PayoutOdd($homeGoals, $awayGoals, $HomeUp,$HdpGoal,$HdpUnit);
            case 'W2':
                return $this->calculateW2PayoutOdd($homeGoals, $awayGoals, $HomeUp,$HdpGoal,$HdpUnit);
            case 'Over':
                if($totalGoals > $GpGoal){
                    return 2.0;
                }elseif($totalGoals == $GpGoal){
                    return (1.0 + ($GpUnit / 100));
                }else{
                    return 0.0;
                }
            case 'Under':
                if($totalGoals < $GpGoal){
                    return 2.0;
                }elseif($totalGoals == $GpGoal){
                    return (1.0 + ($GpUnit / 100));
                }else{
                    return 0.0;
                }
        }
    }

    protected function calculateW1PayoutOdd($homeGoals, $awayGoals, $HomeUp,$HdpGoal,$HdpUnit) {
        $goalDifference = $homeGoals - $awayGoals;
    
        if ($HomeUp == true) {
            if ($goalDifference > $HdpGoal) {
                return 2.0;
            } elseif ($goalDifference == $HdpGoal) {
                return (1.0 + ($HdpUnit / 100));
            } elseif ($goalDifference == 0 && $HdpGoal == 0) {
                return (1.0 + ($HdpUnit / 100));
            } else {
                return 0.0;
            }
        } elseif ($HomeUp == false) {
            $adjustedGoalDifference = -$goalDifference;
    
            if ($adjustedGoalDifference > $HdpGoal) {
                return 0.0;
            } elseif ($adjustedGoalDifference == $HdpGoal) {
                return (1.0 + ($HdpUnit / 100));
            } elseif ($adjustedGoalDifference == 0 && $HdpGoal == 0) {
                return (1.0 + ($HdpUnit / 100));
            } else {
                return 2.0;
            }
        }
    }
    
    protected function calculateW2PayoutOdd($homeGoals, $awayGoals, $HomeUp,$HdpGoal,$HdpUnit) {
        $goalDifference = $awayGoals - $homeGoals;
    
        if ($HomeUp == false) {
            if ($goalDifference > $HdpGoal) {
                return 2.0;
            } elseif ($goalDifference == $HdpGoal) {
                return (1.0 + ($HdpUnit / 100));
            } elseif ($goalDifference == 0 && $HdpGoal == 0) {
                return (1.0 + ($HdpUnit / 100));
            } else {
                return 0.0;
            }
        } elseif ($HomeUp == true) {
            $adjustedGoalDifference = -$goalDifference;
    
            if ($adjustedGoalDifference > $HdpGoal) {
                return 0.0;
            } elseif ($adjustedGoalDifference == $HdpGoal) {
                return (1.0 + ($HdpUnit / 100));
            } elseif ($adjustedGoalDifference == 0 && $HdpGoal == 0) {
                return (1.0 + ($HdpUnit / 100));
            } else {
                return 2.0;
            }
        }
    }
    protected function allMatchesCompleted($betId)
    {
        $count = Accumulator::where('bet_id', $betId)
            ->whereHas('match', function($query) {
                $query->where('status', '!=', 'completed');
            })
            ->count();
        return $count == 0;
    }

    protected function processAccumulatorPayout($betId)
    {
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
                    'amount'=>$bet->wining_amount,
                    'Win'=>$bet->wining_amount,
                    'balance'=>$user->balance
                ]);
            }
        });
    }
    

    protected function getTaxRate($leagueName)
    {
        $topLeagues = ['England Premier League', 'Spain La Liga', 'Italy Serie A', 'German Bundesliga', 'France Ligue 1', 'Champions League'];
        return in_array($leagueName, $topLeagues) ? 0.06 : 0.08;
    }       
    protected function getAccumulatorTaxRate($matchCount)
    {
        if ($matchCount < 3) {
            return 0.15;
        } elseif ($matchCount <= 11) {
            return 0.20; 
        } else {
            return 0.0; 
        }
    }
//  Function to get the single commission recipient and distribute the remaining commission up the hierarchy
    private function getSingleCommissionRecipient($userId, $commissionType, $remainingCommission)
    {
        $user = User::find($userId);
        $userCommissionRate = SingleCommissions::where('user_id', $userId)->value($commissionType);

        if ($userCommissionRate >= $remainingCommission) {
            return ['user' => $user, 'commission' => $remainingCommission];
        }

        // Allocate the user's commission and reduce the remaining commission
        $allocatedCommission = $userCommissionRate;
        $remainingCommission -= $allocatedCommission;

        if ($user->created_by !== null && $remainingCommission > 0) {
            // Pass the remaining commission up the hierarchy
            return $this->getSingleCommissionRecipient($user->created_by, $commissionType, $remainingCommission);
        }

        return ['user' => $user, 'commission' => $allocatedCommission];
    }

// Function to get the accumulator commission recipient and distribute the remaining commission up the hierarchy
    private function getAccumulatorCommissionRecipient($userId, $matchCount, $remainingCommission)
    {
        $user = User::find($userId);
        $userCommissionRate = MixBetCommissions::where('user_id', $userId)->value('m' . $matchCount);

        if ($userCommissionRate >= $remainingCommission) {
            return ['user' => $user, 'commission' => $remainingCommission];
        }

        // Allocate the user's commission and reduce the remaining commission
        $allocatedCommission = $userCommissionRate;
        $remainingCommission -= $allocatedCommission;

        if ($user->created_by !== null && $remainingCommission > 0) {
            // Pass the remaining commission up the hierarchy
            return $this->getAccumulatorCommissionRecipient($user->created_by, $matchCount, $remainingCommission);
        }

        return ['user' => $user, 'commission' => $allocatedCommission];
    }

// Function to calculate the single bet commission
    public function calculateSingleBetCommission($userId, $betAmount, $league)
    {
        $topLeagues = ['England Premier League', 'Spain La Liga', 'Italy Serie A', 'German Bundesliga', 'France Ligue 1', 'Champions League'];
        $isHigh = in_array($league, $topLeagues);

        $commissionType = $isHigh ? 'high' : 'low';
        $remainingCommission = 0.02 * $betAmount;  // Constant rate is 2%

        $commissionData = $this->getSingleCommissionRecipient($userId, $commissionType, $remainingCommission);

        if ($commissionData['user']) {
            $commissionData['user']->balance += $commissionData['commission'];
            $commissionData['user']->save();

            return $commissionData['commission'];
        }

        return 0;
    }

// Function to calculate the accumulator bet commission
    public function calculateAccumulatorBetCommission($userId, $betAmount, $matchCount)
    {
        $commissionRate = ($matchCount == 2) ? 0.07 : 0.15;
        $remainingCommission = $commissionRate * $betAmount;

        $commissionData = $this->getAccumulatorCommissionRecipient($userId, $matchCount, $remainingCommission);

        if ($commissionData['user']) {
            $commissionData['user']->balance += $commissionData['commission'];
            $commissionData['user']->save();

            Log::info('Accumulator bet commission calculated', [
                'user_id' => $userId,
                'commission_user_id' => $commissionData['user']->id,
                'commission_amount' => $commissionData['commission'],
            ]);

            return $commissionData['commission'];
        }

        return 0;
    }
}
