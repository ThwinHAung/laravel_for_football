<?php

namespace App\Services;

use App\Models\Accumulator;
use App\Models\Matches;
use App\Models\Bets;
use App\Models\SingleCommissions;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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

        $accumulators = Accumulator::where('match_id', $match->id)->where('status', 'Accepted')->get();
        foreach($accumulators as $accumulator) {
            $this->calculateAccumulatorPayout($accumulator, $match);

            if ($this->allMatchesCompleted($accumulator->bet_id)) {
                $this->processAccumulatorPayout($accumulator->bet_id);
            }
        }

    }

    protected function calculateSingleBetPayout(Bets $bet, Matches $match)
    {
        $leagueName = DB::table('matches')
        ->join('leagues', 'matches.league_id', '=', 'leagues.id')
        ->where('matches.id', $match->id)
        ->value('leagues.name');

        $potentialWinningAmount = $this->calculatePotentialWinningAmount($bet, $match);

        if ($potentialWinningAmount > $bet->amount) {
            $winningAmount = $potentialWinningAmount - $bet->amount;
            $taxRate = $this->getTaxRate($leagueName);
            $taxAmount = $winningAmount * $taxRate;
            $netWinnings = $winningAmount - $taxAmount;
            
            $bet->status = 'Win';
            $bet->wining_amount = $netWinnings + $bet->amount; 
            $this->updateUserBalance($bet->user_id, $netWinnings + $bet->amount);
        } else if($potentialWinningAmount > 0){
            $bet->status = 'Win';
            $bet->wining_amount = $potentialWinningAmount;
            $this->updateUserBalance($bet->user_id, $bet->wining_amount);
        }else{
            $bet->status = 'Lose';
        }

        $bet->save();

        $this->calculateSingleBetCommission($bet->user_id, $bet->amount, $leagueName);
    }

    protected function calculatePotentialWinningAmount(Bets $bet,Matches $match){
        $amount = $bet->amount;
        $homeGoals = $match->home_goals;
        $awayGoals = $match->away_goals;
        $totalGoals = $homeGoals + $awayGoals;
        $specialOddTeam = $match->special_odd_team;
        $specialOddFirstDigit = $match->special_odd_first_digit;
        $specialOddSign = $match->special_odd_sign;
        $specialOddValue = $match->special_odd_last_digit;
        $overUnder_first_digit = $match->over_under_first_digit;
        $overUnder_sign = $match->over_under_odd_sign;
        $overUnder_value = $match->over_under_last_digit;


        switch($bet->selected_outcome){
            case 'W1':
                return $this->calculateW1Payout($amount, $homeGoals, $awayGoals, $specialOddTeam, $specialOddFirstDigit, $specialOddSign, $specialOddValue);
            case 'W2':
                return $this->calculateW2Payout($amount, $homeGoals, $awayGoals, $specialOddTeam, $specialOddFirstDigit, $specialOddSign, $specialOddValue);
            case 'Over':
                if($totalGoals > $overUnder_first_digit){
                    return $amount * 2;
                }elseif($totalGoals == $overUnder_first_digit){
                    return $amount * (1 + ($overUnder_sign == "+" ? $overUnder_value : -$overUnder_value) / 100);
                }else{
                    return 0;
                }
            case 'Under':
                if($totalGoals < $overUnder_first_digit){
                    return $amount * 2;
                }elseif($totalGoals == $overUnder_first_digit){
                    return $amount * (1 + ($overUnder_sign == "+" ? $overUnder_value : -$overUnder_value) / 100);
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

    protected function calculateW1Payout($amount, $homeGoals, $awayGoals, $specialOddTeam, $specialOddFirstDigit, $specialOddSign, $specialOddValue) {
        $goalDifference = $homeGoals - $awayGoals;
    
        if ($specialOddTeam == 'H') {
            if ($goalDifference > $specialOddFirstDigit) {
                return $amount * 2; 
            } elseif ($goalDifference == $specialOddFirstDigit) {
                return $amount * (1 + ($specialOddSign == '+' ? $specialOddValue : -$specialOddValue) / 100); 
            } elseif ($goalDifference == 0 && $specialOddFirstDigit == 0) {
                return $amount * (1 + ($specialOddSign == '+' ? $specialOddValue : -$specialOddValue) / 100); 
            } else {
                return 0; 
            }
        } elseif ($specialOddTeam == 'A') {
            $adjustedGoalDifference = -$goalDifference;
    
            if ($adjustedGoalDifference > $specialOddFirstDigit) {
                return 0; 
            } elseif ($adjustedGoalDifference == $specialOddFirstDigit) {
                return $amount * (1 + ($specialOddSign == '+' ? -$specialOddValue : $specialOddValue) / 100); 
            } elseif ($adjustedGoalDifference == 0 && $specialOddFirstDigit == 0) {
                return $amount * (1 + ($specialOddSign == '+' ? -$specialOddValue : $specialOddValue) / 100);
            } else {
                return $amount * 2; 
            }
        }
    }
    

    protected function calculateW2Payout($amount, $homeGoals, $awayGoals, $specialOddTeam, $specialOddFirstDigit, $specialOddSign, $specialOddValue) {
        $goalDifference = $awayGoals - $homeGoals;
    
        if ($specialOddTeam == 'A') {
            if ($goalDifference > $specialOddFirstDigit) {
                return $amount * 2; 
            } elseif ($goalDifference == $specialOddFirstDigit) {
                return $amount * (1 + ($specialOddSign == '+' ? $specialOddValue : -$specialOddValue) / 100); 
            } elseif ($goalDifference == 0 && $specialOddFirstDigit == 0) {
                return $amount * (1 + ($specialOddSign == '+' ? $specialOddValue : -$specialOddValue) / 100); 
            } else {
                return 0; 
            }
        } elseif ($specialOddTeam == 'H') {
            $adjustedGoalDifference = -$goalDifference;
    
            if ($adjustedGoalDifference > $specialOddFirstDigit) {
                return 0; 
            } elseif ($adjustedGoalDifference == $specialOddFirstDigit) {
                return $amount * (1 + ($specialOddSign == '+' ? -$specialOddValue : $specialOddValue) / 100); 
            } elseif ($adjustedGoalDifference == 0 && $specialOddFirstDigit == 0) {
                return $amount * (1 + ($specialOddSign == '+' ? -$specialOddValue : $specialOddValue) / 100); 
            } else {
                return $amount * 2; 
            }
        }
    }

    protected function calculateAccumulatorPayout(Accumulator $accumulator,Matches $match){
        $potentialWiningOdd = $this->calculatePotentialWinningOdd($accumulator,$match);
        $betId = $accumulator->bet_id;
        $bet = Bets::find($betId);
        $user_id = $bet->user_id;
        $bet_amount = $bet->amount;

        $accumulatorBets = Accumulator::where('bet_id', $betId)->get();
        $matchCount = $accumulatorBets->count;
        $this->updateAgentBalanceAccumulator($matchCount,$user_id,$bet_amount);

        if($potentialWiningOdd > 0){
            $accumulator->status = 'Win';
        }else{
            $accumulator->status = 'Lose';
        }
        $accumulator->wining_odd = $potentialWiningOdd;
        $accumulator->save();
    }
    
    protected function calculatePotentialWinningOdd(Accumulator $accumulator,Matches $match){
        $homeGoals = $match->home_goals;
        $awayGoals = $match->away_goals;
        $totalGoals = $homeGoals + $awayGoals;
        $specialOddTeam = $match->special_odd_team;
        $specialOddFirstDigit = $match->special_odd_first_digit;
        $specialOddSign = $match->special_odd_sign;
        $specialOddValue = $match->special_odd_last_digit;
        $overUnder_first_digit = $match->over_under_first_digit;
        $overUnder_sign = $match->over_under_odd_sign;
        $overUnder_value = $match->over_under_last_digit;

        switch($accumulator->selected_outcome){
            case 'W1':
                return $this->calculateW1PayoutOdd($homeGoals, $awayGoals, $specialOddTeam, $specialOddFirstDigit, $specialOddSign, $specialOddValue);
            case 'W2':
                return $this->calculateW2PayoutOdd($homeGoals, $awayGoals, $specialOddTeam, $specialOddFirstDigit, $specialOddSign, $specialOddValue);
            case 'Over':
                if($totalGoals > $overUnder_first_digit){
                    return 2.0;
                }elseif($totalGoals == $overUnder_first_digit){
                    return (1.0 + ($overUnder_sign == "+" ? $overUnder_value : -$overUnder_value) / 100);
                }else{
                    return 0.0;
                }
            case 'Under':
                if($totalGoals < $overUnder_first_digit){
                    return 2.0;
                }elseif($totalGoals == $overUnder_first_digit){
                    return (1.0 + ($overUnder_sign == "+" ? $overUnder_value : -$overUnder_value) / 100);
                }else{
                    return 0.0;
                }
        }
    }

    protected function calculateW1PayoutOdd($homeGoals, $awayGoals, $specialOddTeam, $specialOddFirstDigit, $specialOddSign, $specialOddValue) {
        $goalDifference = $homeGoals - $awayGoals;
    
        if ($specialOddTeam == 'H') {
            if ($goalDifference > $specialOddFirstDigit) {
                return 2.0;
            } elseif ($goalDifference == $specialOddFirstDigit) {
                return (1.0 + ($specialOddSign == '+' ? $specialOddValue : -$specialOddValue) / 100);
            } elseif ($goalDifference == 0 && $specialOddFirstDigit == 0) {
                return (1.0 + ($specialOddSign == '+' ? $specialOddValue : -$specialOddValue) / 100);
            } else {
                return 0.0;
            }
        } elseif ($specialOddTeam == 'A') {
            $adjustedGoalDifference = -$goalDifference;
    
            if ($adjustedGoalDifference > $specialOddFirstDigit) {
                return 0.0;
            } elseif ($adjustedGoalDifference == $specialOddFirstDigit) {
                return (1.0 + ($specialOddSign == '+' ? -$specialOddValue : $specialOddValue) / 100);
            } elseif ($adjustedGoalDifference == 0 && $specialOddFirstDigit == 0) {
                return (1.0 + ($specialOddSign == '+' ? -$specialOddValue : $specialOddValue) / 100);
            } else {
                return 2.0;
            }
        }
    }
    
    protected function calculateW2PayoutOdd($homeGoals, $awayGoals, $specialOddTeam, $specialOddFirstDigit, $specialOddSign, $specialOddValue) {
        $goalDifference = $awayGoals - $homeGoals;
    
        if ($specialOddTeam == 'A') {
            if ($goalDifference > $specialOddFirstDigit) {
                return 2.0;
            } elseif ($goalDifference == $specialOddFirstDigit) {
                return (1.0 + ($specialOddSign == '+' ? $specialOddValue : -$specialOddValue) / 100);
            } elseif ($goalDifference == 0 && $specialOddFirstDigit == 0) {
                return (1.0 + ($specialOddSign == '+' ? $specialOddValue : -$specialOddValue) / 100);
            } else {
                return 0.0;
            }
        } elseif ($specialOddTeam == 'H') {
            $adjustedGoalDifference = -$goalDifference;
    
            if ($adjustedGoalDifference > $specialOddFirstDigit) {
                return 0.0;
            } elseif ($adjustedGoalDifference == $specialOddFirstDigit) {
                return (1.0 + ($specialOddSign == '+' ? -$specialOddValue : $specialOddValue) / 100);
            } elseif ($adjustedGoalDifference == 0 && $specialOddFirstDigit == 0) {
                return (1.0 + ($specialOddSign == '+' ? -$specialOddValue : $specialOddValue) / 100);
            } else {
                return 2.0;
            }
        }
    }
    protected function allMatchesCompleted($betId)
    {
        return Accumulator::where('bet_id', $betId)
            ->whereHas('match', function($query) {
                $query->where('status', '!=', 'completed');
            })
            ->count() == 0;
    }

    protected function processAccumulatorPayout($betId)
    {
        $accumulatorBets = Accumulator::where('bet_id', $betId)->get();
        $bet = Bets::find($betId);

        if ($bet) {
            $totalOdds = $accumulatorBets->reduce(function ($carry, $item) {
                return $carry * $item->wining_odd;
            }, 1.0);
    
            $winningAmount = $bet->amount * $totalOdds;
    
            if ($accumulatorBets->where('status', 'Lose')->count() > 0) {
                $winningAmount = 0;
                $bet->status = 'Lose';
            } else {
                $bet->status = 'Win';
                $matchCount = $accumulatorBets->count;
                $taxRate = $this->getAccumulatorTaxRate($matchCount);
                $taxAmount = $winningAmount * $taxRate;
                $netWinnings = $winningAmount - $taxAmount;
                $bet->wining_amount = $netWinnings;
                $this->updateUserBalance($bet->user_id, $netWinnings);
            }
    
            $bet->save();
        }
    }

    protected function updateAgentBalanceAccumulator($matchCount,$user_id,$bet_amount){
        if($matchCount < 4){
            $user = User::find($user_id);
            if ($user && $user->created_by) {
                $agent = User::find($user->created_by);
                if ($agent) {
                    $commissionAmount = $bet_amount * 0.07;
                    $agent->balance += $$commissionAmount;
                    $agent->save();
                }
            }
        }elseif($matchCount < 12){
            $user = User::find($user_id);
            if ($user && $user->created_by) {
                $agent = User::find($user->created_by);
                if ($agent) {
                    $commissionAmount = $bet_amount * 0.15;
                    $agent->balance += $$commissionAmount;
                    $agent->save();
                }
            }
        }

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
    }public function calculateSingleBetCommission($userId, $betAmount, $league)
    {
        $topLeagues = ['England Premier League', 'Spain La Liga', 'Italy Serie A', 'German Bundesliga', 'France Ligue 1', 'Champions League'];
        $isHigh = in_array($league, $topLeagues);
    
        $commissionType = $isHigh ? 'high' : 'low';
        $commissionUser = $this->getSingleCommissionRecipient($userId, $commissionType);
    
        if ($commissionUser) {
            $commissionAmount = $betAmount * 0.01;
    
            $commissionUser->balance += $commissionAmount;
            $commissionUser->save();
    
            return $commissionAmount;
        }
        return 0;
    }
    
    private function getSingleCommissionRecipient($userId, $commissionType)
    {
        $user = User::find($userId);
        $commission = SingleCommissions::where('user_id', $userId)->value($commissionType);
    
        if ($commission == 1) {
            return $user;
        }
        if ($user->created_by !== null) {
            return $this->getSingleCommissionRecipient($user->created_by, $commissionType);
        }
    
        return null;
    }
}
