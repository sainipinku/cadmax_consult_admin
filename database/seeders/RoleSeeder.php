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

        // Create or restore Member role with unique slug safely
        $role = Role::withTrashed()->where('slug', 'member')->first();

        $data = [
            'name' => 'Member',
            'status' => 1,
            'created_by' => $superAdmin->id,
        ];

        if ($role) {
            if ($role->trashed()) {
                $role->restore();
            }
            $role->update($data);
        } else {
            Role::create(array_merge(['slug' => 'member'], $data));
        }

        $this->command->info('Member role created/updated successfully!');
    }
}