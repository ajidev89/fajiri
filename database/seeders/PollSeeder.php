<?php

namespace Database\Seeders;

use App\Enums\Poll\PollStatus;
use App\Enums\Poll\PollType;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Database\Seeder;

class PollSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::whereHas('role', function ($q) {
            $q->where('slug', 'super-admin')->orWhere('name', 'Super Admin');
        })->first() ?? User::first();

        if (!$admin) {
            $this->command->error("No user/admin found to attach polls to.");
            return;
        }

        $pollsData = [
            [
                'title'          => 'Community Development Priorities for 2026',
                'type'           => PollType::RADIO,
                'status'         => PollStatus::ACTIVE,
                'start_date'     => now()->subDays(2),
                'duration_hours' => 720, // 30 days
                'options'        => [
                    'Healthcare & Community Clinics',
                    'Clean Water & Sanitation Infrastructure',
                    'Youth Education & Tech Scholarships',
                    'Agricultural Support & Micro-Grants',
                    'Solar Power & Rural Electrification',
                ],
            ],
            [
                'title'          => 'Which welfare and relief initiatives should we expand next?',
                'type'           => PollType::CHECKBOX,
                'status'         => PollStatus::ACTIVE,
                'start_date'     => now()->subDay(),
                'duration_hours' => 360, // 15 days
                'options'        => [
                    'Emergency Medical Support',
                    'Widow & Orphan Empowerment',
                    'Small Business Startup Grants',
                    'Food Security & Nutrition Drives',
                ],
            ],
            [
                'title'          => 'How satisfied are you with our donation and campaign transparency?',
                'type'           => PollType::RADIO,
                'status'         => PollStatus::ACTIVE,
                'start_date'     => now(),
                'duration_hours' => 480, // 20 days
                'options'        => [
                    'Very Satisfied',
                    'Satisfied',
                    'Neutral',
                    'Needs Improvement',
                ],
            ],
            [
                'title'          => 'What new features or community initiatives would you love to see on Fajiri?',
                'type'           => PollType::LONG_TEXT,
                'status'         => PollStatus::ACTIVE,
                'start_date'     => now(),
                'duration_hours' => 720,
                'options'        => [],
            ],
        ];

        foreach ($pollsData as $data) {
            $options = $data['options'];
            unset($data['options']);

            $poll = Poll::firstOrCreate(
                ['title' => $data['title']],
                array_merge($data, ['added_by' => $admin->id])
            );

            if (!empty($options)) {
                foreach ($options as $index => $label) {
                    $poll->options()->firstOrCreate(
                        ['label' => $label],
                        ['order' => $index]
                    );
                }
            }
        }
    }
}
