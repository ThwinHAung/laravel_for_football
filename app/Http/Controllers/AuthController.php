<?php

namespace App\Http\Controllers;

use App\Models\MixBetCommissions;
use App\Models\SingleCommissions;
use App\Models\Transition;
use Illuminate\Http\Request;
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
            'maxSingleBet.numeric' => 'Max SingleBet amount must be numeric',
            'maxMixBet.numeric' => 'Max MixBet amount must be numeric',
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
            'high.between' => 'High Commission must be between 0 and 1',
            'low.between' => 'Low Commission must be between 0 and 1',
            'mixBet2Commission.between' => 'Mix Bet 2 Commission must be between 0 and 7',
            'mixBet3Commission.between' => 'Mix Bet 3 Commission must be between 0 and 15',
            'mixBet4Commission.between' => 'Mix Bet 4 Commission must be between 0 and 15',
            'mixBet5Commission.between' => 'Mix Bet 5 Commission must be between 0 and 15',
            'mixBet6Commission.between' => 'Mix Bet 6 Commission must be between 0 and 15',
            'mixBet7Commission.between' => 'Mix Bet 7 Commission must be between 0 and 15',
            'mixBet8Commission.between' => 'Mix Bet 8 Commission must be between 0 and 15',
            'mixBet9Commission.between' => 'Mix Bet 9 Commission must be between 0 and 15',
            'mixBet10Commission.between' => 'Mix Bet 10 Commission must be between 0 and 15',
            'mixBet11Commission.between' => 'Mix Bet 11 Commission must be between 0 and 15',
        ];

        $validator = Validator::make($request->all(), [
            "realname" => "required|string",
            "username" => "required|string|unique:users",
            "password" => "required|confirmed|min:8",
            "phone_number" => "required",
            "balance" => "nullable|numeric",
            "maxSingleBet" => "required|numeric:Min",
            "maxMixBet" => "required|numeric",
            "high" => "required|numeric|between:0,1",
            "low" => "required|numeric|between:0,1",
            "mixBet2Commission" => "required|numeric|between:0,7",
            "mixBet3Commission" => "required|numeric|between:0,15",
            "mixBet4Commission" => "required|numeric|between:0,15",
            "mixBet5Commission" => "required|numeric|between:0,15",
            "mixBet6Commission" => "required|numeric|between:0,15",
            "mixBet7Commission" => "required|numeric|between:0,15",
            "mixBet8Commission" => "required|numeric|between:0,15",
            "mixBet9Commission" => "required|numeric|between:0,15",
            "mixBet10Commission" => "required|numeric|between:0,15",
            "mixBet11Commission" => "required|numeric|between:0,15",

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
    
            if ($creator_role !== 'SSSenior') {
                $creator_single_commissions = SingleCommissions::where('user_id', $creator_id)->first();
                $creator_mix_commissions = MixBetCommissions::where('user_id', $creator_id)->first();
    
                if ($request->high > $creator_single_commissions->high) {
                    return response()->json([
                        'message' => "Single bet high commission must be between 0 and {$creator_single_commissions->high}"
                    ], 400);
                }
                
                if($request->low > $creator_single_commissions->low){
                    return response()->json([
                        'message' => "Single bet low commission must be between 0 and {$creator_single_commissions->low}"
                    ], 400);
                }
                
                for ($i = 2; $i <= 11; $i++) {
                    $field = 'm' . $i; 
                    if ($request->input('mixBet' . $i . 'Commission') > $creator_mix_commissions->$field) {
                        return response()->json(['message' => "Mix bet $i commission must be between 0 and {$creator_mix_commissions->$field}"], 400);
                    }
                }
                
                $maxSingleBetRequest = intval($request->maxSingleBet);
                $maxMixBetRequest = intval($request->maxMixBet);
                $maxSingleBetCreator = intval($creator->maxSingleBet);
                $maxMixBetCreator = intval($creator->maxMixBet);

                if($maxSingleBetRequest > $maxSingleBetCreator){
                    return response()->json(['message'=>"Cannot give max Single Bet amount more than limit"],400);
                }
                if($maxMixBetRequest > $maxMixBetCreator){
                    return response()->json(['message'=>"Cannot give max Mix Bet amount more than limit"],400);
                }
            }
            $balance = $request->balance ?? 0;

            if ($creator_role !== 'SSSenior' && $balance > 0) {
                if ($creator->balance < $balance) {
                    return response()->json(['message' => 'Insufficient balance'], 400);
                }
                $creator->balance -= $balance;
                $creator->save();
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
    
            if ($request->balance > 0) {
                Transition::create([
                    'user_id' => $user->id,
                    'description' => 'From ' . $creator->username,
                    'amount'=>$request->balance,
                    'IN'=>$request->balance,
                    'balance'=>$request->balance,
                ]);
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
    
        $user = User::where("username", $request->username)->first(); 
    
        if(!empty($user)){
            if($user->status === 'suspended') {
                return response()->json(["message"=> "Your account is suspended. Contact your agent!"], 403);
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
        return  response()->json(["balance"=>$user_Data->balance],200);
    }
    public function logout(){
        $token = auth()->user()->token();

        $token->revoke();
        return  response()->json(["message" => "User Logged out successfully"],200);
    }
    public function getCreatedUsers(){
    $userId = auth()->user()->id;
    $createdUsers = User::where('created_by', $userId)->select('id','realname','username','balance')->get();

    return response()->json(['created_users' => $createdUsers], 200);
    }
    public function getUserDetails($id){
        $userDetails = User::where('id',$id)->select('realname','username','phone_number','balance','maxSingleBet','maxMixBet','status')->get();
        $userSingleCommissions = SingleCommissions::where('user_id',$id)->select('high','low')->get();
        $userAccumulatorCommissions = MixBetCommissions::where('user_id',$id)->select('m2','m3','m4','m5','m6','m7','m8','m9','m10','m11')->get();
        return response()->json(['user_details'=>$userDetails,'single_commissions'=>$userSingleCommissions,'mix_commissions'=>$userAccumulatorCommissions],200);
    }
    public function change_passowrd_user(Request $request){
        $customMessages = [
            'required' => 'Fill all fields',
            'new_password.confirmed'=>'Password confirmation do not match',
            'new_password.min:8'=>'Password must be at least 8 characters'
        ];
        $validator = Validator::make($request->all(), [
            "current_password"=>"required",
            "new_password"=>"required|confirmed|min:8"
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
        $user_id = auth()->user()->id;
        $user = User::find($user_id);
        if(Hash::check($request->current_password,$user->password)){
            $user->password = Hash::make($request->new_password);
            $user->save();
            return response()->json(['message' => 'Password changed successfully'], 200);
        }else{
            return response()->json(['message' => 'Incorrect current password'], 400);
        }
    }
    public function change_passowrd(Request $request){
        $customMessages = [
            'required' => 'Fill all fields',
            'new_password.confirmed'=>'Password confirmation do not match',
            'new_password.min:8'=>'Password must be at least 8 characters'
        ];
        $validator = Validator::make($request->all(), [
            "user_id" => "required|exists:users,id",
            "new_password"=>"required|confirmed|min:8"
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
        $user = User::find($request->input('user_id'));
        $user->password = Hash::make($request->new_password);
        $user->save();
        return response()->json(['message' => 'Password changed successfully'], 200);
    }
    public function editBasicInfo(Request $request,$id){
        $customMessages = [
            'required'=>'Fill all fields',
            'real.string'=>'Nickname must be String',
            'phone_number.numeric'=>'Phone number must be numeric'
        ];
        $validator = Validator::make($request->all(), [
            "realname" => "required|string",
            "phone_number" => "required|numeric",
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
        $user = User::find($id);
        $user->realname = $request->input('realname');
        $user->phone_number = $request->input('phone_number');
        $user->save();

        return response()->json(['message'=>'Successfully edited'],200);

    }

}
