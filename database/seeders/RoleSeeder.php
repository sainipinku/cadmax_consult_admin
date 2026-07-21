<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\SuperAdmin;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdmin = SuperAdmin::first();
        
        if (!$superAdmin) {
            $this->command->warn('No Super Admin found. Please run SuperAdminSeeder first.');
            return;
        }

        // Create Member role with unique slug
        Role::updateOrCreate(
            ['slug' => 'member'],
            [
                'name' => 'Member',
                'status' => 1,
                'created_by' => $superAdmin->id,
            ]
        );

        $this->command->info('Member role created/updated successfully!');
    }
}