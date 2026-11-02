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
            [
                'name' => 'Demo Company',
                'status' => 'active',
            ]
        );

        $superAdminEmail = env('SUPER_ADMIN_EMAIL', 'superadmin@demo.test');
        $superAdminPassword = env('SUPER_ADMIN_PASSWORD', 'SuperAdmin@Secure2026!');

        User::updateOrCreate(
            ['email' => $superAdminEmail],
            [
                'name' => 'Super Admin',
                'company_id' => null,
                'role' => 'super_admin',
                'password' => Hash::make($superAdminPassword),
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@demo.test'],
            [
                'name' => 'Demo Admin',
                'company_id' => $company->id,
                'role' => 'admin',
                'is_primary_admin' => true,
                'password' => Hash::make('Admin@12345'),
            ]
        );

        User::updateOrCreate(
            ['email' => 'viewer@demo.test'],
            [
                'name' => 'Demo Viewer',
                'company_id' => $company->id,
                'role' => 'viewer',
                'is_primary_admin' => false,
                'password' => Hash::make('Viewer@12345'),
            ]
        );
    }
}