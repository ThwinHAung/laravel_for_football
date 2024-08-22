<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StatusController extends Controller
{
    //
    public function suspend_user(Request $request){
        $validator = Validator::make($request->all(), [
            "user_id"=>"required|exists:users,id",
        ]);
    
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 400);
        }
        $user = User::find($request->input('user_id'));
        $user->status = 'suspended';
        $user->save();
        return response()->json(['message' => 'User suspended successfully'],200);
    }
    public function unsuspend_user(Request $request){
        $validator = Validator::make($request->all(), [
            "user_id"=>"required|exists:users,id",
        ]);
    
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 400);
        }
        $user = User::find($request->input('user_id'));
        $user->status = 'active';
        $user->save();
        return response()->json(['message' => 'User unsuspended successfully'],200);
    }
    
    
    // public function delete_user(Request $request){
    //     $validator = Validator::make($request->all(), [
    //         "user_id"=>"required|exists:users,id",
    //     ]);
    
    //     if ($validator->fails()) {
    //         return response()->json(['message' => $validator->errors()], 400);
    //     }
    //     $user = User::find($request->input('user_id'));
    //     $user->delete();
    //     return response()->json(['message' => 'User soft deleted successfully']);
    // }
    public function getMixBets(Request $request,$username){
        $user = User::where('username', $username)->first();
        if($user){
            $maxSingleBet = $user->maxSingleBet;
            $maxMixBet = $user->maxMixBet;
            return response()->json(['maxSingleBet'=>$maxSingleBet,'maxMixBet'=>$maxMixBet],200);
        }
        else{
            return response()->json(['error'],400);
        }
    }

    public function member_count() {
        $userId = auth()->user()->id;

        $userCount = User::where('created_by', $userId)->count();

        return response()->json(["userCount" => $userCount], 200);
    }

    public function down_line() {
        $authUser = Auth::user();
        $authUserId = $authUser->id;
        $role = $authUser->role->name;
        if($role == 'Agent' || $role == 'Master'){
            $totalBalance = User::where('created_by', $authUserId)->sum('balance');
            return response()->json(["downlineBalance"=>$totalBalance],200);
        }else{
            $totalBalance = DB::select("
            WITH RECURSIVE UserHierarchy AS (
                SELECT 
                    id,
                    balance,
                    created_by
                FROM 
                    users
                WHERE 
                    id = ? -- Start with the current user
                    
                UNION ALL
                
                SELECT 
                    u.id,
                    u.balance,
                    u.created_by
                FROM 
                    users u
                INNER JOIN 
                    UserHierarchy uh 
                    ON u.created_by = uh.id
            )
            SELECT 
                SUM(balance) AS total_balance
            FROM 
                UserHierarchy;
        ", [$authUserId]);
    
        $totalBalance = $totalBalance[0]->total_balance ?? 0;
        return response()->json(['downlineBalance' => $totalBalance], 200);
        }
    }
    

    public function outstanding_balance() {
        $userId = auth()->user()->id;
        $today = Carbon::today()->toDateString();
    
        $totalBetAmountToday = DB::table('bets')
            ->join('users', 'bets.user_id', '=', 'users.id')
            ->where('users.created_by', $userId)
            ->where('bets.status', 'Accepted')
            ->whereDate('bets.created_at', $today)
            ->sum('bets.amount');
    
        $totalBetAmountToday = $totalBetAmountToday ?? 0;
    
        return response()->json(["outstandingBalance" => $totalBetAmountToday], 200);
    }
    
    

}
