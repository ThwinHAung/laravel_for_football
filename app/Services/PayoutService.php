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
        // $this->calculateSingleBetCommission($bet->user_id, $bet->amount, $match->league);
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
    protected function allMatchesCompleted($betId) {
        $count = Accumulator::where('bet_id', $betId)
            ->whereHas('match', function($query) {
                $query->where('IsEnd', false)
                      ->where('IsPost', false);
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
                // $this->calculateAccumulatorBetCommission($bet->user_id,$bet->amount,$matchCount);
            }
        });
    }
    

    protected function getTaxRate($leagueName)
    {
        $topLeagues = ['ENGLISH PREMIER LEAGUE', 'SPAIN LALIGA', 'ITALY SERIE A', 'GERMANY BUNDESLIGA', 'FRANCE LIGUE 1', 'UEFA CHAMPIONS LEAGUE'];
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
    // public function calculateSingleBetCommission($userId, $amount, $leagueName) {
    //     $topLeagues = ['ENGLISH PREMIER LEAGUE', 'SPAIN LALIGA', 'ITALY SERIE A', 'GERMANY BUNDESLIGA', 'FRANCE LIGUE 1', 'UEFA CHAMPIONS LEAGUE'];
    //     $commissionType = in_array($leagueName, $topLeagues) ? 'high' : 'low';
    //     $constantRate = 2.0;
    //     $remainingRate = $constantRate;
    
    //     while ($remainingRate > 0) {

    //         $commission = SingleCommissions::where('user_id', $userId)->first();

    //         if (!$commission) {
    //             break;
    //         }    

    //         $userCommissionRate = $commissionType === 'high' ? $commission->high : $commission->low;

    //         $commissionToGive = min($userCommissionRate, $remainingRate);
    //         $commissionAmount = $amount * ($commissionToGive / 100);

    //         $this->updateUserBalance($userId, $commissionAmount);
    
    //         $remainingRate -= $commissionToGive;
    //         $user = User::find($userId);
    //         $userId = $user->created_by;
            
    //         if (!$userId) {
    //             break;
    //         }
    //     }
    // }

    // public function calculateSingleBetCommission($userId, $amount, $leagueName) {
    //     $topLeagues = ['ENGLISH PREMIER LEAGUE', 'SPAIN LALIGA', 'ITALY SERIE A', 'GERMANY BUNDESLIGA', 'FRANCE LIGUE 1', 'UEFA CHAMPIONS LEAGUE'];
    //     $commissionType = in_array($leagueName, $topLeagues) ? 'high' : 'low';
        
    //     while ($userId) {
    //         // Get the commission rate for the current user
    //         $commission = SingleCommissions::where('user_id', $userId)->first();
    //         $userCommissionRate = $commissionType === 'high' ? $commission->high : $commission->low;
    
    //         // Calculate the commission amount for the current user
    //         $commissionAmount = $amount * ($userCommissionRate / 100);
    
    //         // Update the user's balance with their commission amount
    //         $this->updateUserBalance($userId, $commissionAmount);
    
    //         // Find the parent user to pass the remaining commission to
    //         $user = User::find($userId);
    //         $parentUserId = $user->created_by;
    
    //         // Adjust the user's commission rate by subtracting the given amount
    //         $userId = $parentUserId;
    
    //         // If there's a parent user, calculate the remaining commission to pass up
    //         if ($userId) {
    //             $parentCommission = SingleCommissions::where('user_id', $userId)->first();
    //             $parentCommissionRate = $commissionType === 'high' ? $parentCommission->high : $parentCommission->low;
                
    //             // Subtract the child's rate from the parent's rate
    //             $userCommissionRate = max(0, $parentCommissionRate - $userCommissionRate);
    //         }
    //     }
    // }
    

    // public function calculateSingleBetCommission($userId, $amount, $leagueName) {
    //     $topLeagues = ['ENGLISH PREMIER LEAGUE', 'SPAIN LALIGA', 'ITALY SERIE A', 'GERMANY BUNDESLIGA', 'FRANCE LIGUE 1', 'UEFA CHAMPIONS LEAGUE'];
    //     $commissionType = in_array($leagueName, $topLeagues) ? 'high' : 'low';
    //     $constantRate = 2.0;
    //     $remainingRate = $constantRate;
    //     $parentUserId = $userId;
    
    //     while ($remainingRate > 0 && $parentUserId) {
    //         $commission = SingleCommissions::where('user_id', $parentUserId)->first();
    //         $userCommissionRate = $commissionType === 'high' ? $commission->high : $commission->low;
    
    //         // The current user's commission
    //         $commissionToGive = min($userCommissionRate, $remainingRate);
    //         $commissionAmount = $amount * ($commissionToGive / 100);
    
    //         // Update the balance
    //         $this->updateUserBalance($parentUserId, $commissionAmount);
    
    //         // Deduct from remaining rate for next level
    //         $remainingRate -= $commissionToGive;
    //         $parentUser = User::find($parentUserId);
    //         $parentUserId = $parentUser->created_by;
    
    //         // Update the next user's commission rate
    //         $remainingRate = min($remainingRate, $userCommissionRate);
    //     }
    // }
    
    // public function calculateSingleBetCommission($userId, $amount, $leagueName) {
    //     $topLeagues = ['ENGLISH PREMIER LEAGUE', 'SPAIN LALIGA', 'ITALY SERIE A', 'GERMANY BUNDESLIGA', 'FRANCE LIGUE 1', 'UEFA CHAMPIONS LEAGUE'];
    //     $commissionType = in_array($leagueName, $topLeagues) ? 'high' : 'low';
    //     $remainingRate = 2.0; // This is the constant rate
    
    //     while ($remainingRate > 0) {
    //         $commission = SingleCommissions::where('user_id', $userId)->first();
    //         $userCommissionRate = $commissionType === 'high' ? $commission->high : $commission->low;
    
    //         if ($userCommissionRate > 0) {
    //             // Calculate the amount the current user should get after giving to their child
    //             $commissionToGive = min($userCommissionRate, $remainingRate);
    //             $commissionAmount = $amount * ($commissionToGive / 100);
    
    //             // Update the user's balance with the calculated commission amount
    //             $this->updateUserBalance($userId, $commissionAmount);
    
    //             // Decrease the remaining rate by the amount given to the current user
    //             $remainingRate -= $userCommissionRate;
    //         }
    
    //         // Get the parent user's ID and continue the loop with the parent
    //         $user = User::find($userId);
    //         if (!$user) {
    //             break; // Stop the loop if there is no more parent user
    //         }
    //         $userId = $user->created_by;
    //     }
    // }
    

}
