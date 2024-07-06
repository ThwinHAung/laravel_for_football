<?php

namespace App\Http\Controllers;

use App\Models\SingleCommissions;
use App\Models\Transition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request){
        $validator = Validator::make($request->all(), [
            "realname"=>"required|string",
            "username" => "required|string|unique:users",
            "password" => "required|confirmed|min:8",
            "phone_number" => "required",
            "balance" => "nullable|numeric",
            "maxSingleBet"=>"required|numeric",
            "maxMixBet"=>"required|numeric",
            // "high"=>"required|numeric|in:0,1",
            // "low"=>"required|numeric|in:0,1",
            // "mixBet2Commission"=>"required|numeric|in:0,7",
            // "mixBet3Commission"=>"required|numeric|in:0,15",
            // "mixBet4Commission"=>"required|numeric|in:0,15",
            // "mixBet5Commission"=>"required|numeric|in:0,15",
            // "mixBet6Commission"=>"required|numeric|in:0,15",
            // "mixBet7Commission"=>"required|numeric|in:0,15",
            // "mixBet8Commission"=>"required|numeric|in:0,15",
            // "mixBet9Commission"=>"required|numeric|in:0,15",
            // "mixBet10Commission"=>"required|numeric|in:0,15",
            // "mixBet11Commission"=>"required|numeric|in:0,15",
        ]);
    
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 400);
        }
        $creator_role = auth()->user()->role->name;
        $creator_id = auth()->user()->id;

        $creator = User::find($creator_id);
    
        $role_id = match ($creator_role) {
            "SSSenior" => 2,
            "SSenior" => 3,
            "Senior" => 4,
            "Master" => 5,
            // "Senior Agent"=>6,
            "Agent" => 6,
            default => null,
        };
    
        if ($role_id !== null) {
            $balance = $request->balance ?? 0;
    
            // Check for roles other than SSSenior and validate balance
            if ($creator_role !== 'SSSenior' && $balance > 0) {
                if ($creator->balance < $balance) {
                    return response()->json(['message' => 'Insufficient balance'], 400);
                }
    
                // Deduct balance from creator
                $creator->balance -= $balance;
                $creator->save();
            }
    
            $user = User::create([
                'realname'=>$request->realname,
                'username' => $request->username,
                'password' => bcrypt($request->password),
                'phone_number' => $request->input('phone_number'),
                'balance' => $balance,
                'role_id' => $role_id,
                'created_by' => $creator_id
            ]);

            SingleCommissions::create([
                'user_id'=>$user->id,
                'high'=>$request->high,
                'low'=>$request->low

            ]);
    
            // Record transition for SSSenior only
            if ($creator_role === 'SSSenior') {
                if($request->balance > 0){
                    Transition::create([
                        'user_id' => $user->id,
                        'amount' => $balance,
                    ]);
                }
            }

    
            return response()->json(['message' => 'Signup successful','user_id'=>$user->id], 200);
        } else {
            return response()->json(['message' => 'Invalid role'], 400);
        }
    }
    
    public function login(Request $request){
        $validator = Validator::make($request->all(), [
            "username" => "required|string",
            "password" => "required",
            // "remember_me"=>"boolean",
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 400);
        }
    
        $user = User::where("username", $request->username)
                    ->whereNull('deleted_at') 
                    ->first(); 
    
        if(!empty($user)){
            if($user->status === 'postponed') {
                return response()->json(["message"=> "Your account is postponed"], 403);
            }
    
            if(Hash::check($request->password, $user->password)){
                $token = $user->createToken("usertoken")->accessToken;
                return response()->json(["message"=> "Login Successful","token" => $token,"role"=>$user->role->name,"username"=>$user->username],200);
            }else{
                return response()->json(["message"=> "Invalid Password"], 401);
            }
        }else{
            return response()->json(["message"=> "Invalid User"], 404);
        }
    }

    public function balance(){
        $user_Data = auth()->user();
        return  response()->json(["message" => "Retrieve balance successfully","balance"=>$user_Data->balance],200);
    }
    public function logout(){
        $token = auth()->user()->token();

        $token->revoke();
        return  response()->json(["message" => "User Logged out successfully"],200);
    }
    public function getCreatedUsers(){
    $userId = auth()->user()->id;
    $createdUsers = User::where('created_by', $userId)->whereNull('deleted_at')->select('id','username','phone_number','balance')->get();

    return response()->json(['created_users' => $createdUsers], 200);
    }
    public function change_passowrd(Request $request){
        $validator = Validator::make($request->all(), [
            "current_password"=>"required",
            "new_password"=>"required|confirmed|min:8"
        ]);
    
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 400);
        }
        $user_id = auth()->user()->id;
        $user = User::find($user_id);
        if(Hash::check($request->current_password,$user->password)){
            $user->password = Hash::make($request->new_password);
            $user->save();
            return response()->json(['message' => 'Password changed successfully'], 200);
        }else{
            return response()->json(['message' => 'Current Password is successfully'], 400);
        }
    }

}
