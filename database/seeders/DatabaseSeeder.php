<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::updateOrCreate(
            ['name' => 'Demo Company'],
            ['name' => 'Demo Company']
        );

        User::updateOrCreate(
            ['email' => 'admin@demo.test'],
            [
                'name' => 'Demo Admin',
                'company_id' => $company->id,
                'role' => 'admin',
                'password' => Hash::make('Admin@12345'),
            ]
        );

        User::updateOrCreate(
            ['email' => 'viewer@demo.test'],
            [
                'name' => 'Demo Viewer',
                'company_id' => $company->id,
                'role' => 'viewer',
                'password' => Hash::make('Viewer@12345'),
            ]
        );
    }
}