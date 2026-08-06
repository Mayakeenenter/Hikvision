<?php

namespace App\Console\Commands;

use App\Models\HikvisionEvent;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SeedHikvisionEvents extends Command
{
    protected $signature = 'hikvision:seed {--count=50 : Number of sample events to generate}';

    protected $description = 'Seed the database with realistic sample Hikvision events for testing the dashboard';

    protected array $sampleEmployees = [
        ['id' => '6',  'name' => 'Osama'],
        ['id' => '4',  'name' => 'Ahmad'],
        ['id' => '24', 'name' => 'Mohammed'],
        ['id' => '11', 'name' => 'Sara'],
        ['id' => '17', 'name' => 'Khalid'],
        ['id' => '3',  'name' => 'Fatima'],
        ['id' => '9',  'name' => 'Ali'],
        ['id' => null, 'name' => null], // anonymous events (door open/close)
    ];

    protected array $eventScenarios = [
        [
            'event_type'   => 'Authenticated',
            'status_badge' => 'authenticated',
            'major_type'   => 5,
            'sub_type'     => 75,
            'has_employee' => true,
        ],
        [
            'event_type'   => 'Door Open',
            'status_badge' => 'doorOpen',
            'major_type'   => 5,
            'sub_type'     => 82,
            'has_employee' => false,
        ],
        [
            'event_type'   => 'Door Closed',
            'status_badge' => 'doorClosed',
            'major_type'   => 5,
            'sub_type'     => 83,
            'has_employee' => false,
        ],
        [
            'event_type'   => 'Exit Button Released',
            'status_badge' => 'exitButton',
            'major_type'   => 5,
            'sub_type'     => 84,
            'has_employee' => false,
        ],
        [
            'event_type'   => 'Exit Button Pressed',
            'status_badge' => 'exitButton',
            'major_type'   => 5,
            'sub_type'     => 85,
            'has_employee' => false,
        ],
        [
            'event_type'   => 'Authentication Failed',
            'status_badge' => 'failed',
            'major_type'   => 5,
            'sub_type'     => 76,
            'has_employee' => true,
        ],
    ];

    public function handle(): int
    {
        $count = (int) $this->option('count');

        $this->info("Seeding {$count} sample Hikvision events...");

        $now    = Carbon::now('Asia/Dubai');
        $bar    = $this->output->createProgressBar($count);

        for ($i = 0; $i < $count; $i++) {
            $scenario = $this->eventScenarios[array_rand($this->eventScenarios)];

            $recordedAt = $now->copy()->subMinutes(rand(1, 60 * 24 * 7)); // Random within past 7 days

            if ($scenario['has_employee']) {
                $employees = array_filter($this->sampleEmployees, fn($e) => $e['name'] !== null);
                $employees = array_values($employees);
                $emp = $employees[array_rand($employees)];
            } else {
                $emp = ['id' => null, 'name' => null];
            }

            $cardReaderId = rand(0, 1) === 0 ? '1' : null;

            HikvisionEvent::create([
                'event_id'       => 'seed_' . uniqid(),
                'employee_id'    => $emp['id'],
                'employee_name'  => $emp['name'],
                'card_number'    => null,
                'card_reader_id' => $emp['id'] ? $cardReaderId : null,
                'event_type'     => $scenario['event_type'],
                'sub_type'       => (string) $scenario['sub_type'],
                'major_type'     => (string) $scenario['major_type'],
                'status_badge'   => $scenario['status_badge'],
                'recorded_at'    => $recordedAt,
                'event_date'     => $recordedAt->toDateString(),
                'event_time'     => $recordedAt->format('H:i:s'),
                'remote_host'    => null,
                'raw_payload'    => [
                    'seeded'       => true,
                    'employeeId'   => $emp['id'],
                    'name'         => $emp['name'],
                    'major'        => $scenario['major_type'],
                    'minor'        => $scenario['sub_type'],
                    'time'         => $recordedAt->toIso8601String(),
                ],
            ]);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✓ Successfully seeded {$count} sample events into the database.");
        $this->info('Visit your dashboard at: ' . config('app.url') . '/events');

        return self::SUCCESS;
    }
}
