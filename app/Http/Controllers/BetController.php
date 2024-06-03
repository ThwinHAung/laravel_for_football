<?php

namespace App\Http\Controllers;

use App\Models\Accumulator;
use App\Models\Bets;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
class BetController extends Controller
{
    //
    public function placeSingleBet(Request $request){
        $user_id = auth()->user()->id;
        $request->validate([
            'match_id'=>'required|integer|exists:matches,id',
            'selected_outcome' => 'required|in:W1,W2,Over,Under',
            'amount' => 'required|numeric',
        ]);
        $User = User::find($user_id);
        if ($User->balance < $request->amount) {
            return response()->json(['message' => 'Insufficient balance'], 400);
        }else{
            Bets::create([
                'user_id'=>$user_id,
                'match_id'=>$request->match_id,
                'bet_type'=>'single',
                'selected_outcome'=>$request->selected_outcome,
                'amount'=>$request->amount
            ]);
            $User->balance -= $request->amount;
            $User->save();
            return response()->json(['message' => 'Single bet placed successfully'], 200);
        }
        
    }

    public function placeAccumulatorBet(Request $request){
        $user_id = auth()->user()->id;
        $request->validate([
            'amount' => 'required|numeric',
            'matches' => 'required|array',
            'matches.*.match_id' => 'required|integer|exists:matches,id',
            'matches.*.selected_outcome' => 'required|string|in:W1,W2,Over,Under',
        ]);
        $User = User::find($user_id);
        if ($User->balance < $request->amount) {
            return response()->json(['message' => 'Insufficient balance'], 400);
        }else{
            $bet = Bets::create([
                'user_id' => $user_id,
                'match_id' => null, 
                'bet_type' => 'accumulator',
                'selected_outcome' => 'Null', 
                'amount' => $request->amount,
            ]);
    
            foreach ($request->matches as $match) {
                Accumulator::create([
                    'bet_id' => $bet->id,
                    'match_id' => $match['match_id'],
                    'selected_outcome' => $match['selected_outcome'],
                ]);
            }
            $User->balance -= $request->amount;
            $User->save();
        }

        return response()->json(['message' => 'Accumulator bet placed successfully'], 200);
    }

    public function getBetSlip(Request $request)
    {

        $yesterday = Carbon::yesterday()->startOfDay();
        $today = Carbon::today()->endOfDay();

        // Fetch single bets for the given user for yesterday and today
        $singleBets = Bets::where('user_id', $request->user_id)
            ->where('bet_type', 'single')
            ->whereBetween('created_at', [$yesterday, $today])
            ->get();

        // Fetch accumulator bets for the given user for yesterday and today
        $accumulatorBets = Bets::where('user_id', $request->user_id)
            ->where('bet_type', 'accumulator')
            ->whereBetween('created_at', [$yesterday, $today])
            ->with('accumulators')
            ->get();

        return response()->json([
            'singleBets' => $singleBets,
            'accumulatorBets' => $accumulatorBets
        ]);

        
    }
    

}
