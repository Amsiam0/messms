<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions
        Permission::firstOrCreate(['name' => 'manage_meals']);
        Permission::firstOrCreate(['name' => 'manage_expenses']);
        Permission::firstOrCreate(['name' => 'manage_payments']);
        Permission::firstOrCreate(['name' => 'manage_members']);
        Permission::firstOrCreate(['name' => 'view_reports']);
    }
}
