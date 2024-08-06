<?php

namespace App\Http\Controllers;

use App\Models\Transition;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransitionController extends Controller
{
    //

    public function manageUnits(Request $request)
    {
        $customMessages = [
            'required'=>'Fill all fields',
            'amount.numeric'=>'Transcation amount must be numeric',
            'amount.min'=>'Invalid transaction amount!'

        ];
        $validator = Validator::make($request->all(), [
            "user_id" => "required|exists:users,id",
            "amount" => "required|numeric|min:1000",
            "action" => "required|in:add,remove"
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

        $loggedUserId = auth()->user()->id;
        $loggedUser = User::find($loggedUserId);
        $loggedUserRole = $loggedUser->role->name;
        $user = User::find($request->input('user_id'));
        $amount = $request->input('amount');

        if ($request->action === 'add') {
            if ($loggedUserRole == 'SSSenior') {
                $user->balance += $amount;
                $user->save();

                Transition::create([
                    "user_id" => $request->user_id,
                    "description"=>"From ".$loggedUser->username,
                    "amount" => $amount,
                    "IN"=>$amount,
                    "balance"=>$user->balance
                ]);
                return response()->json(['message' => 'Units added successfully'], 200);
            } else {
                if ($loggedUser->balance >= $amount) {
                    $loggedUser->balance -= $amount;
                    $user->balance += $amount;
                    $loggedUser->save();
                    $user->save();

                    Transition::create([
                        "user_id" => $request->user_id,
                        "description"=>"From ".$loggedUser->username,
                        "amount" => $amount,
                        "IN"=>$amount,
                        "balance"=>$user->balance
                    ]);
                    
                    return response()->json(['message' => 'Units added successfully'], 200);
                } else {
                    return response()->json(['message' => 'Insufficient balance'], 400);
                }   
            }

        } elseif ($request->action === 'remove') {
            if ($loggedUserRole == 'SSSenior') {
                if ($user->balance >= $amount) {
                    $user->balance -= $amount;
                    $user->save();

                    Transition::create([
                        "user_id" => $request->user_id,
                        "description"=>"From ".$loggedUser->username,
                        "amount" => $amount,
                        "OUT"=>$amount,
                        "balance"=>$user->balance
                    ]);

                    return response()->json(['message' => 'Units reduced successfully'], 200);
                } else {
                    return response()->json(['message' => 'Insufficient balance'], 400);
                }
            } else {
                if ($user->balance >= $amount) {
                    $user->balance -= $amount;
                    $loggedUser->balance += $amount;
                    $user->save();
                    $loggedUser->save();

                    Transition::create([
                        "user_id" => $request->user_id,
                        "description"=>"From ".$loggedUser->username,
                        "amount" => $amount,
                        "OUT"=>$amount,
                        "balance"=>$user->balance
                    ]);
                    return response()->json(['message' => 'Units reduced successfully'], 200);
                } else {
                    return response()->json(['message' => 'Insufficient balance'], 400);
                }
            }
        }
    }
    public function fetchTransaction($userId){
        $transactions = Transition::where('user_id', $userId)->select('IN','OUT','Bet','Win','commission','balance','created_at')->get();

        $formattedTransactions = $transactions->map(function ($transaction) {
            return [
                'IN' => $transaction->IN,
                'OUT' => $transaction->OUT,
                'Bet' => $transaction->Bet,
                'Win' => $transaction->Win,
                'commission' => $transaction->commission,
                'balance' => $transaction->balance,
                'created_at' => $transaction->created_at->format('Y-m-d'),  // Custom date format
            ];
        });
        return response()->json($formattedTransactions);
        
    }
}
