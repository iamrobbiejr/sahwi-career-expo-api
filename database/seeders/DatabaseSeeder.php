<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {


        // Create roles
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'student']);
       Role::create(['name' => 'professional']);
       Role::create(['name' => 'company_rep']);
       Role::create(['name' => 'university']);

       $this->call(PermissionRoleSeeder::class);

    }
}
