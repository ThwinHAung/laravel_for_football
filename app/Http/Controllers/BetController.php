<?php

namespace App\Http\Controllers;

use App\Models\Accumulator;
use App\Models\Bets;
use App\Models\Matches;
use App\Models\MixBetCommissions;
use App\Models\SingleCommissions;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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
                'selected_outcome' => null, 
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

    public function getBetSlip($username)
    {
        $endOfToday = Carbon::tomorrow()->subSecond();
        $startOfYesterday = Carbon::yesterday();

        $user = User::where('username', $username)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user_id = $user->id;
    
        $singleBets = DB::table('bets')
        ->select(
            'id',
            'match_id',
            'selected_outcome',
            'amount',
            'status',
            'wining_amount',
            DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as created_at")
        )
        ->where('user_id', $user_id)
        ->where('bet_type', 'single')
        ->whereBetween('created_at', [$startOfYesterday, $endOfToday])
        ->get();


        $accumulatorBets = DB::table('bets')
        ->leftJoin('accumulators', 'bets.id', '=', 'accumulators.bet_id')
        ->select(
            'bets.id',
            'bets.amount',
            'bets.status',
            'bets.wining_amount',
            'bets.created_at',
            DB::raw('COUNT(accumulators.id) AS match_count')
        )
        ->where('bets.user_id', $user_id)
        ->where('bets.bet_type', 'accumulator')
        ->whereBetween('bets.created_at', [$startOfYesterday, $endOfToday])
        ->groupBy('bets.id', 'bets.amount', 'bets.status', 'bets.wining_amount','bets.created_at')
        ->get();

        

        return response()->json(['messsage'=>'Successful fetch','singleBets' => $singleBets,'accumulatorBets'=>$accumulatorBets]);

        
    }
    public function getSingleBetSlip($bet_id){
        $bet = DB::table('bets')
        ->join('matches', 'bets.match_id', '=', 'matches.id')
        ->join('leagues', 'matches.league_id', '=', 'leagues.id')
        ->where('bets.id', $bet_id)
        ->select(
            'bets.selected_outcome',
            'bets.amount',
            'bets.status',
            'bets.wining_amount',
            'matches.home_match',
            'matches.away_match',
            'matches.match_time',
            'matches.special_odd_team',
            'matches.special_odd_first_digit',
            'matches.special_odd_sign',
            'matches.special_odd_last_digit',
            'matches.over_under_first_digit',
            'matches.over_under_sign',
            'matches.over_under_last_digit',
            'matches.home_goals',
            'matches.away_goals',
            'matches.status as match_status',
            'leagues.name as league_name'
        )
        ->first();

        if ($bet) {
            $response = [
                'message' => 'Successful fetch',
                'bet' => [
                    'selected_outcome' => $bet->selected_outcome,
                    'amount' => $bet->amount,
                    'user_status' => $bet->status,
                    'wining_amount' => $bet->wining_amount,
                    'league_name' => $bet->league_name,
                    'home_match' => $bet->home_match,
                    'away_match' => $bet->away_match,
                    'match_time' => $bet->match_time,
                    'special_odd_team' => $bet->special_odd_team,
                    'special_odd_first_digit' => $bet->special_odd_first_digit,
                    'special_odd_sign' => $bet->special_odd_sign,
                    'special_odd_last_digit' => $bet->special_odd_last_digit,
                    'over_under_first_digit' => $bet->over_under_first_digit,
                    'over_under_sign' => $bet->over_under_sign,
                    'over_under_last_digit' => $bet->over_under_last_digit,
                    'home_goals' => $bet->home_goals,
                    'away_goals' => $bet->away_goals,
                    'status' => $bet->status,
                ]
            ];
            
            return response()->json($response);
        } else {
            return response()->json([
                'message' => 'Bet not found',
            ], 404);
        }

    }
    public function getAccumulatorBetSlip($bet_id)
    {
        $bet = DB::table('bets')
            ->where('id', $bet_id)
            ->where('bet_type', 'accumulator')
            ->first();
    
        if (!$bet) {
            return response()->json([
                'message' => 'Accumulator bet slip not found',
            ], 404);
        }

        $accumulator_entries = DB::table('accumulators')
            ->join('matches', 'accumulators.match_id', '=', 'matches.id')
            ->join('leagues', 'matches.league_id', '=', 'leagues.id')
            ->where('accumulators.bet_id', $bet_id)
            ->select(
                'accumulators.selected_outcome',
                'matches.home_match',
                'matches.away_match',
                'matches.match_time',
                'matches.special_odd_team',
                'matches.special_odd_first_digit',
                'matches.special_odd_sign',
                'matches.special_odd_last_digit',
                'matches.over_under_first_digit',
                'matches.over_under_sign',
                'matches.over_under_last_digit',
                'matches.home_goals',
                'matches.away_goals',
                'matches.status as match_status',
                'leagues.name as league_name'
            )
            ->get();
    
        if ($accumulator_entries->isEmpty()) {
            return response()->json([
                'message' => 'No matches found for the accumulator bet slip',
            ], 404);
        }
    
        $response = [
            'message' => 'Successful fetch',
            'bet' => [
                'amount' => $bet->amount,
                'status' => $bet->status,
                'wining_amount' => $bet->wining_amount,
            ],
            'accumulator_entries' => $accumulator_entries->map(function ($entry) {
                return [
                    'selected_outcome' => $entry->selected_outcome,
                    'league_name' => $entry->league_name,
                    'home_match' => $entry->home_match,
                    'away_match' => $entry->away_match,
                    'match_time' => $entry->match_time,
                    'special_odd_team' => $entry->special_odd_team,
                    'special_odd_first_digit' => $entry->special_odd_first_digit,
                    'special_odd_sign' => $entry->special_odd_sign,
                    'special_odd_last_digit' => $entry->special_odd_last_digit,
                    'over_under_first_digit' => $entry->over_under_first_digit,
                    'over_under_sign' => $entry->over_under_sign,
                    'over_under_last_digit' => $entry->over_under_last_digit,
                    'home_goals' => $entry->home_goals,
                    'away_goals' => $entry->away_goals,
                ];
            }),
        ];
    
        return response()->json($response);
    }

    public function editBetLimit(Request $request){
        $customMessages = [
            'required'=>'Fill all fields',
            'maxSingleBet.numeric'=>'Max Amount for SingleBet must be Numeric',
            'maxMixBet.numeric'=>'Max Amount for MixBet must be Numeric'
        ];
        $validator = Validator::make($request->all(), [
            "user_id" => "required|exists:users,id",
            "maxSingleBet" => "required|numeric",
            "maxMixBet" => "required|numeric",
        ],$customMessages);
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
        
            $hasRequiredError = false;
            foreach ($errors as $error) {
                if ($error === 'Fill all fields') {
                    $hasRequiredError = true;
                    break;
                }
            }
        
            if ($hasRequiredError) {
                return response()->json(['message' => 'Fill all fields'], 400);
            }
        
            return response()->json(['message' => $error], 400);
        }
        $creator_role = auth()->user()->role->name;
        $creator_id = auth()->user()->id;
        $creator = User::find($creator_id);
        $maxSingleBetRequest = intval($request->maxSingleBet);
        $maxMixBetRequest = intval($request->maxMixBet);
        if($creator_role !== 'SSSenior'){
            $maxSingleBetCreator = intval($creator->maxSingleBet);
            $maxMixBetCreator = intval($creator->maxMixBet);
            if(($maxSingleBetRequest > $maxSingleBetCreator) || ($maxMixBetRequest > $maxMixBetCreator)){
                return response()->json(['message'=>"You cannot give max bet amount more than you have"],400);
            }else{
                $user = User::find($request->input('user_id'));
                $user->maxSingleBet = $request->input('maxSingleBet');
                $user->maxMixBet = $request->input('maxMixBet');
                $user->save();
                return response()->json(['message'=>'Successfully updated'],200);
            }
        }else{
            $user = User::find($request->input('user_id'));
            $user->maxSingleBet = $request->input('maxSingleBet');
            $user->maxMixBet = $request->input('maxMixBet');
            $user->save();
            return response()->json(['message'=>'Successfully updated'],200);
        }





    }

    public function SingleCommissions(Request $request){
        $customMessages = [
            'required'=>'Fill all fields',
            'commissions.in'=>'Single Bet Commission must be 0 or 1',
            'commissions.numeric'=>'SingleBetCommission must be Numeric',
            'high_commissions.in'=>'Single Bet Commission must be 0 or 1',
            'high_commissions.numeric'=>'SingleBetCommission must be Numeric'
        ];
    
        $validator = Validator::make($request->all(), [
            "user_id" => "required|exists:users,id",
            "commissions" => "required|numeric|in:0,1",
            "high_commissions" => "required|numeric|in:0,1",
        ], $customMessages);
    
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
    
            $hasRequiredError = false;
            foreach ($errors as $error) {
                if ($error === 'Fill all fields') {
                    $hasRequiredError = true;
                    break;
                }
            }
    
            if ($hasRequiredError) {
                return response()->json(['message' => 'Fill all fields'], 400);
            }
    
            return response()->json(['message' => $errors[0]], 400);
        }
    
        $child = SingleCommissions::where('user_id', $request->input('user_id'))->first();
        if (!$child) {
            return response()->json(['message' => 'Child record not found'], 400);
        }
        
        $authUser = auth()->user();
        $auth_role = $authUser->role->name;
    
        $normalCommissionResponse = null;
        $highCommissionResponse = null;
    
        // Normal Commissions logic
        if ($auth_role != 'SSSenior') {
            $parent = SingleCommissions::where('user_id', $authUser->id)->first();
            if ($request->commissions != 0) {
                if($parent->low != 0){
                    $child->low = $request->input('commissions');
                    $child->save();
                    $normalCommissionResponse = ['message' => 'Normal commissions transferred', 'status' => 200];
                }else{
                    $normalCommissionResponse = ['message' => 'Normal commissions cannot transferred', 'status' => 400];
                }
            } else {
                if ($child->low != 0) {
                    $child->low = 0;
                    $child->save();
                    $normalCommissionResponse = ['message' => 'Normal commissions reduced', 'status' => 200];
                } else {
                    $normalCommissionResponse = ['message' => 'Normal commissions cannot be reduced', 'status' => 400];
                }
            }
        } else {
            if ($request->commissions != 0) {
                $child->low = $request->input('commissions');
                $child->save();
                $normalCommissionResponse = ['message' => 'Normal commissions transferred', 'status' => 200];
            } else {
                if ($child->low != 0) {
                    $child->low = 0;
                    $child->save();
                    $normalCommissionResponse = ['message' => 'Normal commissions reduced', 'status' => 200];
                } else {
                    $normalCommissionResponse = ['message' => 'Normal commissions cannot be reduced', 'status' => 400];
                }
            }
        }
    
        // High Commissions logic
        if ($auth_role != 'SSSenior') {
            $parent = SingleCommissions::where('user_id', $authUser->id)->first();
            if ($request->high_commissions != 0) {
                if ($parent->high != 0) {
                    $child->high = $request->input('high_commissions');
                    $child->save();
                    $highCommissionResponse = ['message' => 'High commissions transferred', 'status' => 200];
                } else {
                    $highCommissionResponse = ['message' => 'High commissions cannot be transferred', 'status' => 400];
                }
            } else {
                if ($child->high != 0) {
                    $child->high = 0;
                    $child->save();
                    $highCommissionResponse = ['message' => 'High commissions reduced', 'status' => 200];
                } else {
                    $highCommissionResponse = ['message' => 'High commissions cannot be reduced', 'status' => 400];
                }
            }
        } else {
            if ($request->high_commissions != 0) {
                $child->high = $request->input('high_commissions');
                $child->save();
                $highCommissionResponse = ['message' => 'High commissions transferred', 'status' => 200];
            } else {
                if ($child->high != 0) {
                    $child->high = 0;
                    $child->save();
                    $highCommissionResponse = ['message' => 'High commissions reduced', 'status' => 200];
                } else {
                    $highCommissionResponse = ['message' => 'High commissions cannot be reduced', 'status' => 400];
                }
            }
        }
    
        return response()->json([
            'normal_commissions' => $normalCommissionResponse,
            'high_commissions' => $highCommissionResponse,
        ], 200);
    }
    
    

    public function editMix3to11Commissions(Request $request){
        $customMessages = [
            'required'=>'Fill all fields',
            'commission.in'=>'Mix Bet Commission must be 0 or 15',
            'commission.numeric'=>'MixBetCommissions must be Numeric'
        ];
        $validator = Validator::make($request->all(), [
            "user_id" => "required|exists:users,id",
            "match_type" => "required|in:m3,m4,m5,m6,m7,m8,m9,m10,m11",
            "commission" => "required|numeric|in:0,15",
        ],$customMessages);
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
        
            $hasRequiredError = false;
            foreach ($errors as $error) {
                if ($error === 'Fill all fields') {
                    $hasRequiredError = true;
                    break;
                }
            }
        
            if ($hasRequiredError) {
                return response()->json(['message' => 'Fill all fields'], 400);
            }
        
            return response()->json(['message' => $error], 400);
        }
        $child = MixBetCommissions::where('user_id',$request->input('user_id'))->first();
        $matchType = $request->input('match_type');
        $authUser = Auth::user();
        $auth_role = $authUser->role;
        

        if($auth_role != 'SSSenior'){
            $parent = MixBetCommissions::where('user_id',$authUser->id)->first();
            if($request->commission != 0){
                if($parent->$matchType != 0){
                    $child->$matchType = $request->input('commission');              
                    $child->save();
                    return response()->json(['message' => 'Commissions transferred'], 200);
                }else{
                    return response()->json(['message' => 'Commissions cannot transferred'], 400);
                }
            }else{
                if($child->$matchType != 0){
                    $child->$matchType = 0;
                    $child->save();
                    return response()->json(['message' => 'Commissions reduced'], 200);
                }else{
                    return response()->json(['message' => 'Commissions cannot reduced'], 400);
                }
            }
        }else{
            if($request->commission != 0){
                    $child->$matchType = $request->input('commission');
                    $child->save();
                    return response()->json(['message' => 'Commissions transferred'], 200);
            }else{
                if($child->$matchType != 0){
                    $child->$matchType = 0;
                    $child->save();
                    return response()->json(['message' => 'Commissions reduced'], 200);
                }else{
                    return response()->json(['message' => 'Commissions cannot reduced'], 400);
                }
            }  
        }
    }
    public function editMix2Commissions(Request $request){
        $customMessages = [
            'required'=>'Fill all fields',
            'commission.in'=>'Mix Bet Commission must be 0 or 7',
            'commission.numeric'=>'MixBetCommission must be Numeric'
        ];
        $validator = Validator::make($request->all(), [
            "user_id" => "required|exists:users,id",
            "match_type" => "required|in:m2",
            "commission" => "required|numeric|in:0,7",
        ],$customMessages);
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
        
            $hasRequiredError = false;
            foreach ($errors as $error) {
                if ($error === 'Fill all fields') {
                    $hasRequiredError = true;
                    break;
                }
            }
        
            if ($hasRequiredError) {
                return response()->json(['message' => 'Fill all fields'], 400);
            }
        
            return response()->json(['message' => $error], 400);
        }
        $child = MixBetCommissions::where('user_id',$request->input('user_id'))->first();
        $matchType = $request->input('match_type');
        $authUser = Auth::user();
        $auth_role = $authUser->role;


        if($auth_role != 'SSSenior'){
            $parent = MixBetCommissions::where('user_id',$authUser->id)->first();
            if($request->commission != 0){
                if($parent->$matchType != 0){
                    $child->$matchType = $request->input('commission');                
                    $child->save();
                    return response()->json(['message' => 'Commissions transferred'], 200);
                }else{
                    return response()->json(['message' => 'Commissions cannot transferred'], 400);
                }

            }else{
                if($child->$matchType != 0){
                    $child->$matchType = 0;
                    $child->save();
                    return response()->json(['message' => 'Commissions reduced'], 200);
                }else{
                    return response()->json(['message' => 'Commissions cannot reduced'], 400);
                }
            }
        }else{
            if($request->commission != 0){
                    $child->$matchType = $request->input('commission');
                    $child->save();
                    return response()->json(['message' => 'Commissions transferred'], 200);
            }else{
                if($child->$matchType != 0){
                    $child->$matchType = 0;
                    $child->save();
                    return response()->json(['message' => 'Commissions reduced'], 200);
                }else{
                    return response()->json(['message' => 'Commissions cannot reduced'], 400);
                }
            }  
        }
    }
    
}  