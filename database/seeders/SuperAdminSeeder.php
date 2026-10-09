<?php

namespace Database\Seeders;

use App\Models\SuperAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdmin = SuperAdmin::where('email', 'superadmin@gmail.com')
            ->orWhere('phone', '7733844020')
            ->first();

        $data = [
            'roles' => ['admin'],
            'name' => 'Super Admin',
            'email' => 'superadmin@gmail.com',
            'phone' => '7733844020',
            'whatsapp_phone' => '7733844020',
            'password' => Hash::make('superadmin@123'),
        ];

        if ($superAdmin) {
            $superAdmin->update($data);
        } else {
            SuperAdmin::create($data);
        }
    }
}
