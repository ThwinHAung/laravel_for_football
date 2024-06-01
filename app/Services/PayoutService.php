<?php
namespace App\Services;

use App\Models\Matches;
use App\Models\Bets;
use App\Models\Accumulator;
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

        foreach($singleBets as $singleBet){
            $this->calculateSingleBetPayout($singleBet, $match);
        }
    }

    protected function calculateSingleBetPayout(Bets $bet,Matches $match){
        $potentialWinningAmount = $this->calculatePotentialWinningAmount($bet, $match);

        if ($potentialWinningAmount > 0) {
            $bet->status = 'Win';
            $bet->winning_amount = $potentialWinningAmount;
            $this->updateUserBalance($bet->user_id, $bet->winning_amount, 'payout');
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
        $special_odd_first_digit = $match->special_odd_first_digit;
        $special_odd_sign = $match->special_odd_sign;
        $special_odd_value = $match->special_odd_last_sign;
        $overUnder_first_digit = $match->over_under_first_digit;
        $overUnder_sign = $match->over_under_odd_sign;
        $overUnder_value = $match->over_under_last_sign;


        switch($bet->selected_outcome){
            case 'W1':
                return 0;
            case 'W2':
                return 0;
            case 'Over':
                if($totalGoals > $overUnder_first_digit){
                    return $amount * 2;
                }elseif($totalGoals == $overUnder_first_digit){
                    return $amount * ($overUnder_sign == "+" ? 1 + ($overUnder_value / 100) : 1 - ($overUnder_value / 100));
                }else{
                    return 0;
                }
            case 'Under':
                if($totalGoals < $overUnder_first_digit){
                    return $amount * 2;
                }elseif($totalGoals == $overUnder_first_digit){
                    return $amount * ($overUnder_sign == "+" ? 1 + ($overUnder_value / 100) : 1 - ($overUnder_value / 100));
                }else{
                    return 0;
                }
        }
    }
    protected function updateUserBalance($userId, $amount, $type){
        $user = User::find($userId);
        if ($user) {
            $newBalance = $user->balance + $amount;
            $user->balance = $newBalance;
            $user->save();
        }
    }
}
