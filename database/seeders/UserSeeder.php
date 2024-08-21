<?php

namespace Database\Seeders;

use App\Models\Role;
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
    // public function run()
    // {
    //     //chges
    //     //
    //     $user =[
    //         "realname"=>"YYK",
    //         "username"=>"AllFather47",
    //         "password"=>Hash::make("supersuperadmin"),
    //         "phone_number"=>"0629183200",
    //         "balance"=>0.0,
    //         "role_id"=>1,
    //     ];
    //     User::create($user);
    // }
    public function run()
    {
        // Fetch roles
        $roles = Role::pluck('id', 'name')->toArray();

        // Create users for each role
        $users = [
            // SSSenior Role
            [
                'realname' => 'SSSenior User 1',
                'username' => 'sssenior1',
                'password' => Hash::make('password'),
                'phone_number' => '09987654321',
                'balance' => 1000.00,
                'role_id' => $roles['SSSenior'],
                'created_by' => null // Top level user, created by no one
            ],
            [
                'realname' => 'SSenior User 1',
                'username' => 'ssenior1',
                'password' => Hash::make('password'),
                'phone_number' => '09987654322',
                'balance' => 1000.00,
                'role_id' => $roles['SSenior'],
                'created_by' => 1
            ],

            // SSenior Role
            [
                'realname' => 'SSenior User 2',
                'username' => 'ssenior2',
                'password' => Hash::make('password'),
                'phone_number' => '09876543210',
                'balance' => 800.00,
                'role_id' => $roles['SSenior'],
                'created_by' => 1 // Created by SSSenior User 1
            ],
            [
                'realname' => 'Senior User 1',
                'username' => 'senior1',
                'password' => Hash::make('password'),
                'phone_number' => '09876543211',
                'balance' => 800.00,
                'role_id' => $roles['SSenior'],
                'created_by' => 2 // Created by SSSenior User 2
            ],

            // Senior Role
            [
                'realname' => 'Senior User 2',
                'username' => 'senior2',
                'password' => Hash::make('password'),
                'phone_number' => '09765432109',
                'balance' => 600.00,
                'role_id' => $roles['Senior'],
                'created_by' => 2 // Created by SSenior User 1
            ],
            [
                'realname' => 'Senior User 3',
                'username' => 'senior3',
                'password' => Hash::make('password'),
                'phone_number' => '09765432110',
                'balance' => 600.00,
                'role_id' => $roles['Senior'],
                'created_by' => 3 // Created by SSenior User 2
            ],
            [
                'realname' => 'Senior User 4',
                'username' => 'senior4',
                'password' => Hash::make('password'),
                'phone_number' => '09765432110',
                'balance' => 600.00,
                'role_id' => $roles['Senior'],
                'created_by' => 3 // Created by SSenior User 2
            ],

            // Master Role
            [
                'realname' => 'Master User 1',
                'username' => 'master1',
                'password' => Hash::make('password'),
                'phone_number' => '09654321098',
                'balance' => 400.00,
                'role_id' => $roles['Master'],
                'created_by' => 5 // Created by Senior User 1
            ],
            [
                'realname' => 'Master User 2',
                'username' => 'master2',
                'password' => Hash::make('password'),
                'phone_number' => '09654321099',
                'balance' => 400.00,
                'role_id' => $roles['Master'],
                'created_by' => 6 // Created by Senior User 2
            ],
            [
                'realname' => 'Master User 3',
                'username' => 'master3',
                'password' => Hash::make('password'),
                'phone_number' => '09654321099',
                'balance' => 400.00,
                'role_id' => $roles['Master'],
                'created_by' => 6 // Created by Senior User 2
            ],
            [
                'realname' => 'Master User 4',
                'username' => 'master4',
                'password' => Hash::make('password'),
                'phone_number' => '09654321099',
                'balance' => 400.00,
                'role_id' => $roles['Master'],
                'created_by' => 6 // Created by Senior User 2
            ],
            [
                'realname' => 'Master User 2',
                'username' => 'master2',
                'password' => Hash::make('password'),
                'phone_number' => '09654321099',
                'balance' => 400.00,
                'role_id' => $roles['Master'],
                'created_by' => 6 // Created by Senior User 2
            ],
            [
                'realname' => 'Master User 2',
                'username' => 'master2',
                'password' => Hash::make('password'),
                'phone_number' => '09654321099',
                'balance' => 400.00,
                'role_id' => $roles['Master'],
                'created_by' => 6 // Created by Senior User 2
            ],
            [
                'realname' => 'Master User 2',
                'username' => 'master2',
                'password' => Hash::make('password'),
                'phone_number' => '09654321099',
                'balance' => 400.00,
                'role_id' => $roles['Master'],
                'created_by' => 6 // Created by Senior User 2
            ],
            [
                'realname' => 'Master User 2',
                'username' => 'master2',
                'password' => Hash::make('password'),
                'phone_number' => '09654321099',
                'balance' => 400.00,
                'role_id' => $roles['Master'],
                'created_by' => 6 // Created by Senior User 2
            ],

            // Agent Role
            [
                'realname' => 'Agent User 1',
                'username' => 'agent1',
                'password' => Hash::make('password'),
                'phone_number' => '09543210987',
                'balance' => 200.00,
                'role_id' => $roles['Agent'],
                'created_by' => 7 // Created by Master User 1
            ],
            [
                'realname' => 'Agent User 2',
                'username' => 'agent2',
                'password' => Hash::make('password'),
                'phone_number' => '09543210988',
                'balance' => 200.00,
                'role_id' => $roles['Agent'],
                'created_by' => 8 // Created by Master User 2
            ],

            // Regular User Role
            [
                'realname' => 'Regular User 1',
                'username' => 'user1',
                'password' => Hash::make('password'),
                'phone_number' => '09432109876',
                'balance' => 50.00,
                'role_id' => $roles['User'],
                'created_by' => 9 // Created by Agent User 1
            ],
            [
                'realname' => 'Regular User 2',
                'username' => 'user2',
                'password' => Hash::make('password'),
                'phone_number' => '09432109877',
                'balance' => 50.00,
                'role_id' => $roles['User'],
                'created_by' => 10 // Created by Agent User 2
            ],
        ];

        // Insert users into the database
        foreach ($users as $user) {
            User::create($user);
        }
    }
}
