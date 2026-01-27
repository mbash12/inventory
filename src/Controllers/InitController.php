<?php

namespace Src\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;


class InitController extends Controller
{
    public function index()
    {
        try {
            DB::table('users')->insert([
                'id' => 1,
                'name' => 'admin',
                'position' => 'admin',
                'email' => 'basoridotcom@gmail.com',
                'password' => Hash::make('Basori12!!'),
            ]);
            DB::table('warehouses')->insert([
                'id' => 1,
                'name' => 'Manufacture',
                'storage' => FALSE,
            ]);
            DB::table('warehouses')->insert([
                'id' => 2,
                'name' => 'Client',
                'storage' => FALSE,
            ]);
            DB::table('warehouses')->insert([
                'id' => 3,
                'name' => 'Pelangi',
                'storage' => TRUE,
            ]);
            DB::table('warehouses')->insert([
                'id' => 4,
                'name' => 'Chika',
                'storage' => TRUE,
            ]);
            DB::table('warehouses')->insert([
                'id' => 5,
                'name' => 'Mika',
                'storage' => TRUE,
            ]);
            DB::table('warehouses')->insert([
                'id' => 6,
                'name' => 'Mia',
                'storage' => TRUE,
            ]);
            return "OK";
        } catch (\Throwable $th) {
            return "FAILED";
        }
    }
}
