<?php

namespace App\Http\Controllers;


use App\Models\Leagues;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeagueController extends Controller
{
    //
    public function add_league(Request $request){
        $validator = Validator::make($request->all(),[
            "name"=>"required|unique:leagues",
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 400);
        }
        
        Leagues::create([
            "name"=> $request->name,
        ]);
        return response()->json(['message'=> 'add successful'],200);
    }
    public function retrieves_league(){
        $leagues = Leagues::select('id', 'name')->get();
        return response()->json(['message'=> 'retrieve successfully','leagues'=>$leagues],200);
    }
    

}
