<?php

namespace App\Services;

use App\Models\Accumulator;
use App\Models\Matches;
use App\Models\Bets;
use App\Models\Commissions;
use App\Models\MixBetCommissions;
use App\Models\Report;
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
        $winningAmount = $potentialWinningAmount - $bet->amount;
        $commission_id = $this->calculateSingleBetCommission($bet, $bet->user_id, $winningAmount,$match);

        if ($potentialWinningAmount > $bet->amount) {

            $taxRate = $this->getTaxRate($match);
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
            Report::create([
                'user_id'=>$bet->user_id,
                'bet_id'=>$bet->id,
                'commissions_id'=>$commission_id,
                'turnover'=>$bet->amount,
                'valid_amount'=> $winningAmount,
                'win_loss'=> $netWinnings,
                'type'=> 'Los'
,            ]);
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
            Report::create([
                'user_id'=>$bet->user_id,
                'bet_id'=>$bet->id,
                'commissions_id'=>$commission_id,
                'turnover'=>$bet->amount,
                'valid_amount'=> $potentialWinningAmount,
                'win_loss'=> $potentialWinningAmount,
                'type'=> 'Win'
,            ]);
        }else{
            $bet->status = 'Lose';
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
        }

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
                    return $amount * (($GpUnit > 0) ? (1.0 - ($GpUnit / 100)) : (1.0 + abs($GpUnit / 100)));
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
            } elseif ($goalDifference == 0 && $HdpGoal == 0) {
                return $amount * (1 + ($HdpUnit / 100)); 
            }elseif ($goalDifference == $HdpGoal) {
                return $amount * (1 + ($HdpUnit / 100));
            } else {
                return 0; 
            }
        } elseif ($HomeUp == false) {
            $adjustedGoalDifference = -$goalDifference;
    
            if ($adjustedGoalDifference > $HdpGoal) {
                return 0; 
            } elseif ($adjustedGoalDifference == 0 && $HdpGoal == 0) {
                return $amount * (($HdpUnit > 0) ? (1.0 - ($HdpUnit / 100)) : (1.0 + abs($HdpUnit) / 100));
            }elseif ($adjustedGoalDifference == $HdpGoal) {
                return $amount * (($HdpUnit > 0) ? (1.0 - ($HdpUnit / 100)) : (1.0 + abs($HdpUnit) / 100));
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
            }elseif ($goalDifference == 0 && $HdpGoal == 0) {
                return $amount * (1 + ($HdpUnit / 100)); 
            } elseif ($goalDifference == $HdpGoal) {
                return $amount * (1 + ($HdpUnit / 100)); 
            }  else {
                return 0; 
            }
        } elseif ($HomeUp == true) {
            $adjustedGoalDifference = -$goalDifference;
    
            if ($adjustedGoalDifference > $HdpGoal) {
                return 0; 
            } elseif ($adjustedGoalDifference == 0 && $HdpGoal == 0) {
                return $amount * (($HdpUnit > 0) ? (1.0 - ($HdpUnit / 100)) : (1.0 + abs($HdpUnit) / 100));
            }
            elseif ($adjustedGoalDifference == $HdpGoal) {
                return $amount * (($HdpUnit > 0) ? (1.0 - ($HdpUnit / 100)) : (1.0 + abs($HdpUnit) / 100));
            }  else {
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
                    return (($GpUnit > 0) ? (1.0 - ($GpUnit / 100)) : (1.0 + abs($GpUnit) / 100));
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
            } elseif ($goalDifference == 0 && $HdpGoal == 0) {
                return (1.0 + ($HdpUnit / 100));
            } elseif ($goalDifference == $HdpGoal) {
                return (1.0 + ($HdpUnit / 100));
            } else {
                return 0.0;
            }
        } elseif ($HomeUp == false) {
            $adjustedGoalDifference = -$goalDifference;
    
            if ($adjustedGoalDifference > $HdpGoal) {
                return 0.0;
            } elseif ($adjustedGoalDifference == 0 && $HdpGoal == 0) {
                return (($HdpUnit > 0) ? (1.0 - ($HdpUnit / 100)) : (1.0 + abs($HdpUnit) / 100));
            } elseif ($adjustedGoalDifference == $HdpGoal) {
                return (($HdpUnit > 0) ? (1.0 - ($HdpUnit / 100)) : (1.0 + abs($HdpUnit) / 100));
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
            } elseif ($adjustedGoalDifference == 0 && $HdpGoal == 0) {
                return (($HdpUnit > 0) ? (1.0 - ($HdpUnit / 100)) : (1.0 + abs($HdpUnit) / 100));
            } elseif ($adjustedGoalDifference == $HdpGoal) {
                return (($HdpUnit > 0) ? (1.0 - ($HdpUnit / 100)) : (1.0 + abs($HdpUnit) / 100));
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
                $matchCount = $accumulatorBets->count();
                $commission_id = $this->calculateAccumulatorBetCommission($bet,$bet->user_id,$bet->amount,$matchCount);
    
                if ($accumulatorBets->where('status', 'Lose')->count() > 0) {
                    $bet->status = 'Lose';
                    $bet->wining_amount = 0;
                    $bet->status = 'Lose';
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
    
    protected function getTaxRate(Matches $match)
    {
        return $match->high ? 0.05 : 0.08;
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
    protected function calculateSingleBetCommission(Bets $bet, $userId, $betAmount, Matches $match)
{
    $commissionType = $match->high ? 'high' : 'low';

    $user = User::find($userId);
    $currentRole = $user->role;
    $currentCommission = SingleCommissions::where('user_id', $user->id)->first();

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
                $currentCommission = SingleCommissions::where('user_id', $user->id)->first();
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
            'description' => 'Commission (Bet ID: ' . $bet->id . ')',
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
            $currentCommission = SingleCommissions::where('user_id', $user->id)->first();
        } else {
            break;
        }
    }
    $commissionRecord = Commissions::create($commissionData);

    return $commissionRecord->id;
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
