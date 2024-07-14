<?php

namespace App\Http\Controllers;

use App\Models\MixBetCommissions;
use App\Models\SingleCommissions;
use App\Models\Transition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request) {

        $customMessages = [
            'required' => 'Fill all fields',
            'realname.string'=>'Nickname must be String',
            'username.unique' => 'Username is already selected',
            'password.min' => 'Password must be at least 8 characters',
            'password.confirmed'=>'Password must be matched',
            'balance.numeric' => 'Balance must be numeric',
            'maxSingleBet.numeric' => 'Max Bet Amount must be numeric',
            'maxMixBet.numeric' => 'Max Bet Amount must be numeric',
            'high.numeric '=> 'Commissions percent must be numeric',
            'low.numeric' => 'Commissions percent must be numeric',
            'mixBet2Commission.numeric' => 'Commissions percent must be numeric',
            'mixBet3Commission.numeric' => 'Commissions percent must be numeric',
            'mixBet4Commission.numeric' => 'Commissions percent must be numeric',
            'mixBet5Commission.numeric' => 'Commissions percent must be numeric',
            'mixBet6Commission.numeric' => 'Commissions percent must be numeric',
            'mixBet7Commission.numeric' => 'Commissions percent must be numeric',
            'mixBet8Commission.numeric' => 'Commissions percent must be numeric',
            'mixBet9Commission.numeric' => 'Commissions percent must be numeric',
            'mixBet10Commission.numeric' => 'Commissions percent must be numeric',
            'mixBet11Commission.numeric' => 'Commissions percent must be numeric',
            'high.in' => 'High must be 0 or 1',
            'low.in' => 'Low must be 0 or 1',
            'mixBet2Commission.in' => 'Mix Bet 2 Commission must be 0 or 7',
            'mixBet3Commission.in' => 'Mix Bet 3 Commission must be 0 or 15',
            'mixBet4Commission.in' => 'Mix Bet 4 Commission must be 0 or 15',
            'mixBet5Commission.in' => 'Mix Bet 5 Commission must be 0 or 15',
            'mixBet6Commission.in' => 'Mix Bet 6 Commission must be 0 or 15',
            'mixBet7Commission.in' => 'Mix Bet 7 Commission must be 0 or 15',
            'mixBet8Commission.in' => 'Mix Bet 8 Commission must be 0 or 15',
            'mixBet9Commission.in' => 'Mix Bet 9 Commission must be 0 or 15',
            'mixBet10Commission.in' => 'Mix Bet 10 Commission must be 0 or 15',
            'mixBet11Commission.in' => 'Mix Bet 11 Commission must be 0 or 15',
        ];

        $validator = Validator::make($request->all(), [
            "realname" => "required|string",
            "username" => "required|string|unique:users",
            "password" => "required|confirmed|min:8",
            "phone_number" => "required",
            "balance" => "nullable|numeric",
            "maxSingleBet" => "required|numeric",
            "maxMixBet" => "required|numeric",
            "high" => "required|numeric|in:0,1",
            "low" => "required|numeric|in:0,1",
            "mixBet2Commission" => "required|numeric|in:0,7",
            "mixBet3Commission" => "required|numeric|in:0,15",
            "mixBet4Commission" => "required|numeric|in:0,15",
            "mixBet5Commission" => "required|numeric|in:0,15",
            "mixBet6Commission" => "required|numeric|in:0,15",
            "mixBet7Commission" => "required|numeric|in:0,15",
            "mixBet8Commission" => "required|numeric|in:0,15",
            "mixBet9Commission" => "required|numeric|in:0,15",
            "mixBet10Commission" => "required|numeric|in:0,15",
            "mixBet11Commission" => "required|numeric|in:0,15",

        ],$customMessages);
    
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
        
            // Check if there are any 'required' errors
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
    
        $role_id = match ($creator_role) {
            "SSSenior" => 2,
            "SSenior" => 3,
            "Senior" => 4,
            "Master" => 5,
            "Agent" => 6,
            default => null,
        };
    
        if ($role_id !== null) {
            $balance = $request->balance ?? 0;
    
            if ($creator_role !== 'SSSenior' && $balance > 0) {
                if ($creator->balance < $balance) {
                    return response()->json(['message' => 'Insufficient balance'], 400);
                }
                $creator->balance -= $balance;
                $creator->save();
            }
    
            if ($creator_role !== 'SSSenior') {
                $creator_single_commissions = SingleCommissions::where('user_id', $creator_id)->first();
                $creator_mix_commissions = MixBetCommissions::where('user_id', $creator_id)->first();
    
                if ($creator_single_commissions->high == 0 && $request->high != 0) {
                    return response()->json(['message' => 'High single bet commission cannot be passed'], 400);
                }
                if ($creator_single_commissions->low == 0 && $request->low != 0) {
                    return response()->json(['message' => 'Low single bet commission cannot be passed'], 400);
                }
                
                for ($i = 2; $i <= 11; $i++) {
                    $field = 'm' . $i;
                    if ($creator_mix_commissions->$field == 0 && $request->input('mixBet' . $i . 'Commission') != 0) {
                        return response()->json(['message' => "Accumulator bet commission for $i matches cannot be passed"], 400);
                    }
                }
                if(($request->maxSingleBet > $creator->maxSingleBet) || ($request->maxMixBet > $creator->maxMixBet)){
                    return response()->json(['message'=>"You cannot give max bet amount more than you have"],400);
                }
                
            }
            $user = User::create([
                'realname' => $request->realname,
                'username' => $request->username,
                'password' => bcrypt($request->password),
                'phone_number' => $request->input('phone_number'),
                'balance' => $balance,
                'role_id' => $role_id,
                'created_by' => $creator_id,
                'maxSingleBet'=>$request->maxSingleBet,
                'maxMixBet'=>$request->maxMixBet,
            ]);
    
                SingleCommissions::create([
                    'user_id' => $user->id,
                    'high' => $request->high,
                    'low' => $request->low
                ]);
    
                MixBetCommissions::create([
                    'user_id' => $user->id,
                    "m2" => $request->input('mixBet2Commission'),
                    "m3" => $request->input('mixBet3Commission'),
                    "m4" => $request->input('mixBet4Commission'),
                    "m5" => $request->input('mixBet5Commission'),
                    "m6" => $request->input('mixBet6Commission'),
                    "m7" => $request->input('mixBet7Commission'),
                    "m8" => $request->input('mixBet8Commission'),
                    "m9" => $request->input('mixBet9Commission'),
                    "m10" => $request->input('mixBet10Commission'),
                    "m11" => $request->input('mixBet11Commission'),
                ]);
    
            if ($creator_role === 'SSSenior') {
                if ($request->balance > 0) {
                    Transition::create([
                        'user_id' => $user->id,
                        'amount' => $balance,
                    ]);
                }
            }
    
            return response()->json(['message' => 'Signup successful', 'user_id' => $user->id], 200);
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
