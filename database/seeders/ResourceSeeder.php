<?php

namespace Database\Seeders;

use App\Models\Resource;
use Illuminate\Database\Seeder;

class ResourceSeeder extends Seeder
{
    public function run(): void
    {
        $resources = [
            [
                'name' => 'Main Conference Room',
                'capacity' => 10,
            ],
            [
                'name' => 'Small Meeting Room',
                'capacity' => 4,
            ],
            [
                'name' => 'Training Hall',
                'capacity' => 25,
            ],
            [
                'name' => 'VIP Boardroom',
                'capacity' => 6,
            ],
        ];

        foreach ($resources as $resourceData) {
            Resource::firstOrCreate(
                ['name' => $resourceData['name']],
                ['capacity' => $resourceData['capacity']]
            );
        }
    }
}
