<?php

namespace Database\Seeders;

use App\Models\Contractor;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ContractorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (class_exists(Contractor::class)) {
            $contractor = Contractor::where('email', 'contractor@gmail.com')
                ->orWhere('phone', '7733844020')
                ->first();

            $data = [
                'name' => 'Mr. Contractor',
                'email' => 'contractor@gmail.com',
                'phone' => '7733844020',
                'whatsapp_phone' => '7733844020',
                'password' => Hash::make('contractor@123'),
            ];

            if ($contractor) {
                $contractor->update($data);
            } else {
                Contractor::create($data);
            }
        }
    }
}
