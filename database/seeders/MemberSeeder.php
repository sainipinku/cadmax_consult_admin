<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\SuperAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MemberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first super admin to use as creator
        $superAdmin = SuperAdmin::first();
        
        if (!$superAdmin) {
            $this->command->error('No Super Admin found. Please run SuperAdminSeeder first.');
            return;
        }

        // Check if member exists by username, email, or phone to avoid unique constraint violations
        $member = Member::where('username', 'testmember')
            ->orWhere('email', 'member@gmail.com')
            ->orWhere('phone', '9876543210')
            ->first();

        $attributes = [
            'username' => 'testmember',
            'created_by' => $superAdmin->id,
            'name' => 'Test Member',
            'email' => 'member@gmail.com',
            'phone' => '9876543210',
            'password' => Hash::make('member@123'),
            'status' => '1',
            'roles' => ['member'], // Member role slug
            'slug' => 'test-member',
            'dob' => '1995-01-01',
            'gender' => 'male',
        ];

        if ($member) {
            $member->update($attributes);
        } else {
            Member::create(array_merge($attributes, [
                'uuid' => (string) Str::uuid(),
            ]));
        }

        $this->command->info('Test Member created successfully!');
        $this->command->info('Email: member@gmail.com');
        $this->command->info('Password: member@123');
    }
}
