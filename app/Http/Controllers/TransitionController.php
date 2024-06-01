<?php

namespace App\Http\Controllers;

use App\Models\Transition;
use App\Models\User;
use Illuminate\Http\Request;

class TransitionController extends Controller
{
    //

    public function addUnits(Request $request){
        $request->validate([
            "user_id"=>"required|exists:users,id",
            "amount"=>"required|numeric"
        ]);

        $adder_user_role = auth()->user()->role->name;
        if($adder_user_role == 'SSSenior'){
            $user = User::find($request->input('user_id'));
            $user->balance += $request->input('amount');
            $user->save();

            Transition::create([
                "user_id"=>$request->user_id,
                "amount"=>$request->amount,
            ]);

            return response()->json(['message'=> 'Units added successfully',], 200);
        }else{
            $adderId = auth()->user()->id; // Assuming the admin is authenticated
            $adder = User::find($adderId);
            $user = User::find($request->input('user_id'));
            if($adder->balance >= $request->input('amount')) {
                $adder->balance -= $request->input('amount');
                $user->balance += $request->input('amount');
                $adder->save();
                $user->save();
                return response()->json(['message'=> 'Units added successfully',], 200);
            } else {
                return response()->json(['message'=> 'Insufficient balance'], 400);
            }
        }
    }
    public function reducedUnits(Request $request){
        $request->validate([
            "user_id"=>"required|exists:users,id",
            "amount"=>"required|numeric"
        ]);
        $logged_user = auth()->user();
        $logged_userRole = $logged_user->role->name;

        $user = User::find($request->input('user_id'));
        if($logged_userRole == "SSSenior"){
            $user->balance -= $request->input('amount');
            $user->save();
        }else{
            if($request->input('amount') > $user->balance){
                return response()->json(['message'=> 'Insufficient balance'], 400);
            }else{
                $reducer = User::find($logged_user->id);
                $user->balance -= $request->input('amount');
                $reducer->balance += $request->input('amount');
                $user->save();
                $reducer->save();
                return response()->json(['message'=> 'Units reduced successfully',], 200);
            }
        }


    }
}
