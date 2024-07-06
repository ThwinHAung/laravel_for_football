<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //chges
        //
        $user =[
            "realname"=>"YYK",
            "username"=>"AllFather47",
            "password"=>Hash::make("supersuperadmin"),
            "phone_number"=>"0629183200",
            "balance"=>0.0,
            "role_id"=>1,
        ];
        User::create($user);
    }
}
