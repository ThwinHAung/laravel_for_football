<?php

namespace App\Services;

use App\Models\Matches;
use App\Models\Bets;
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
    }

    protected function calculateSingleBetPayout(Bets $bet, Matches $match)
    {
        $potentialWinningAmount = $this->calculatePotentialWinningAmount($bet, $match);

        if ($potentialWinningAmount > 0) {
            $bet->status = 'Win';
            $bet->wining_amount = $potentialWinningAmount;
            $this->updateUserBalance($bet->user_id, $bet->wining_amount);
        } else {
            $bet->status = 'Lose';
        }
        $bet->save();
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
                return $amount * 2; // More than special odd goals difference
            } elseif ($goalDifference == $specialOddFirstDigit) {
                return $amount * (1 + ($specialOddSign == '+' ? $specialOddValue : -$specialOddValue) / 100); // Exactly special odd goals difference
            } elseif ($goalDifference == 0 && $specialOddFirstDigit == 0) {
                return $amount * (1 + ($specialOddSign == '+' ? $specialOddValue : -$specialOddValue) / 100); // Special odds draw case
            } else {
                return 0; // Less than special odd goals difference
            }
        } elseif ($specialOddTeam == 'A') {
            $adjustedGoalDifference = -$goalDifference;
    
            if ($adjustedGoalDifference > $specialOddFirstDigit) {
                return 0; // More than special odd goals difference (opposite case)
            } elseif ($adjustedGoalDifference == $specialOddFirstDigit) {
                return $amount * (1 + ($specialOddSign == '+' ? -$specialOddValue : $specialOddValue) / 100); // Exactly special odd goals difference (opposite case)
            } elseif ($adjustedGoalDifference == 0 && $specialOddFirstDigit == 0) {
                return $amount * (1 + ($specialOddSign == '+' ? -$specialOddValue : $specialOddValue) / 100); // Special odds draw case (opposite)
            } else {
                return $amount * 2; // Less than special odd goals difference (opposite case)
            }
        }
    }
    

    protected function calculateW2Payout($amount, $homeGoals, $awayGoals, $specialOddTeam, $specialOddFirstDigit, $specialOddSign, $specialOddValue) {
        $goalDifference = $awayGoals - $homeGoals;
    
        if ($specialOddTeam == 'A') {
            if ($goalDifference > $specialOddFirstDigit) {
                return $amount * 2; // More than special odd goals difference
            } elseif ($goalDifference == $specialOddFirstDigit) {
                return $amount * (1 + ($specialOddSign == '+' ? $specialOddValue : -$specialOddValue) / 100); // Exactly special odd goals difference
            } elseif ($goalDifference == 0 && $specialOddFirstDigit == 0) {
                return $amount * (1 + ($specialOddSign == '+' ? $specialOddValue : -$specialOddValue) / 100); // Special odds draw case
            } else {
                return 0; // Less than special odd goals difference
            }
        } elseif ($specialOddTeam == 'H') {
            $adjustedGoalDifference = -$goalDifference;
    
            if ($adjustedGoalDifference > $specialOddFirstDigit) {
                return 0; // More than special odd goals difference (opposite case)
            } elseif ($adjustedGoalDifference == $specialOddFirstDigit) {
                return $amount * (1 + ($specialOddSign == '+' ? -$specialOddValue : $specialOddValue) / 100); // Exactly special odd goals difference (opposite case)
            } elseif ($adjustedGoalDifference == 0 && $specialOddFirstDigit == 0) {
                return $amount * (1 + ($specialOddSign == '+' ? -$specialOddValue : $specialOddValue) / 100); // Special odds draw case (opposite)
            } else {
                return $amount * 2; // Less than special odd goals difference (opposite case)
            }
        }
    }
    
}
