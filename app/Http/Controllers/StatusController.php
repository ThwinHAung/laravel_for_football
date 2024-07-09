<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class StatusController extends Controller
{
    //
    public function set_postpone(Request $request){
        $validator = Validator::make($request->all(), [
            "user_id"=>"required|exists:users,id",
        ]);
    
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 400);
        }
        $user = User::find($request->input('user_id'));
        $user->status = 'postponed';
        $user->save();
        return response()->json(['message' => 'User postpone successfully'],200);

    }
    public function unset_postpone(Request $request){
        $validator = Validator::make($request->all(), [
            "user_id"=>"required|exists:users,id",
        ]);
    
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 400);
        }
        $user = User::find($request->input('user_id'));
        $user->status = 'active';
        $user->save();
        return response()->json(['message' => 'User postpone successfully'],200);
    }
    public function delete_user(Request $request){
        $validator = Validator::make($request->all(), [
            "user_id"=>"required|exists:users,id",
        ]);
    
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 400);
        }
        $user = User::find($request->input('user_id'));
        $user->delete();
        return response()->json(['message' => 'User soft deleted successfully']);
    }
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
}
