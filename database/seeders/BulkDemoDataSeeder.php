<?php

namespace Database\Seeders;

use App\Models\ChecklistItem;
use App\Models\CleaningSession;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomPhoto;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class BulkDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Configurable sizes (override via .env) ----
        $ownerCount        = (int) env('SEED_OWNER_COUNT', 5);
        $housekeeperCount  = (int) env('SEED_HK_COUNT', 12);
        $propertyCount     = (int) env('SEED_PROPERTY_COUNT', 60);
        $roomsPerProperty  = [4, 6];   // unique names per property
        $tasksPerRoom      = [4, 7];   // tasks attached per room
        $sessionsPerProp   = [3, 6];   // sessions per property

        // ---- Ensure roles (Spatie) ----
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'owner']);
        Role::firstOrCreate(['name' => 'housekeeper']);

        // ---- Baseline Admin (optional) ----
        if (!User::role('admin')->exists()) {
            User::factory()->create([
                'name'              => 'Admin',
                'email'             => 'admin@example.com',
                'password'          => Hash::make(env('DEMO_USER_PASSWORD', 'password')),
                'email_verified_at' => now(),
            ])->assignRole('admin');
        }

        // ---- Owners + Housekeepers ----
        $owners = User::role('owner')->take($ownerCount)->get();
        if ($owners->count() < $ownerCount) {
            $owners = $owners->concat(
                User::factory()->count($ownerCount - $owners->count())->create()
                    ->each(fn($u) => $u->assignRole('owner'))
            );
        }

        $housekeepers = User::role('housekeeper')->take($housekeeperCount)->get();
        if ($housekeepers->count() < $housekeeperCount) {
            $housekeepers = $housekeepers->concat(
                User::factory()->count($housekeeperCount - $housekeepers->count())->create()
                    ->each(fn($u) => $u->assignRole('housekeeper'))
            );
        }

        // ---- Global catalog: Rooms & Tasks (de-duplicated by name) ----
        $roomNamesPool = [
            'Master Bedroom', 'Guest Bedroom', 'Kitchen', 'Master Bathroom', 'Guest Bathroom', 
            'Living Room', 'Dining Room', 'Home Office', 'Laundry Room', 'Balcony', 
            'Gym', 'Pool Area', 'Garage', 'Garden', 'Game Room'
        ];

        $roomsCatalog = collect($roomNamesPool)->map(function ($name) {
            return Room::firstOrCreate(['name' => $name], ['is_default' => true]);
        });

        $taskNames = [
            'Wipe counters', 'Clean sink', 'Mop floor', 'Clean stovetop', 'Empty trash',
            'Make bed', 'Change linens', 'Dust surfaces', 'Vacuum carpet', 'Scrub toilet',
            'Clean mirror', 'Wipe shower walls', 'Refill toiletries', 'Check inventory',
            'Disinfect handles', 'Open windows for airing', 'Water plants', 'Polish furniture',
            'Steam carpets', 'Clean windows', 'Organize pantry', 'Sweep balcony',
            'Vacuum upholstery', 'Clean baseboards', 'Disinfect equipment', 'Skim pool surface'
        ];

        $tasksCatalog = collect($taskNames)->map(function ($name) {
            return Task::firstOrCreate(
                ['name' => $name],
                [
                    'type'         => in_array($name, ['Check inventory', 'Organize pantry']) ? 'inventory' : 'room',
                    'is_default'   => true,
                    'instructions' => null,
                ]
            );
        });

        // Simple mapping to prefer some tasks per room:
        $roomTaskMap = [
            'Kitchen'         => ['Wipe counters', 'Clean sink', 'Clean stovetop', 'Mop floor', 'Empty trash', 'Disinfect handles', 'Open windows for airing', 'Organize pantry'],
            'Master Bedroom'  => ['Make bed', 'Change linens', 'Dust surfaces', 'Vacuum carpet', 'Disinfect handles', 'Open windows for airing', 'Polish furniture'],
            'Guest Bedroom'   => ['Make bed', 'Change linens', 'Dust surfaces', 'Vacuum carpet', 'Disinfect handles', 'Open windows for airing'],
            'Master Bathroom' => ['Scrub toilet', 'Clean mirror', 'Wipe shower walls', 'Empty trash', 'Refill toiletries', 'Disinfect handles'],
            'Guest Bathroom'  => ['Scrub toilet', 'Clean mirror', 'Wipe shower walls', 'Empty trash', 'Refill toiletries', 'Disinfect handles'],
            'Living Room'     => ['Dust surfaces', 'Vacuum carpet', 'Open windows for airing', 'Vacuum upholstery', 'Polish furniture'],
            'Dining Room'     => ['Dust surfaces', 'Vacuum carpet', 'Open windows for airing', 'Disinfect handles', 'Polish furniture'],
            'Home Office'     => ['Dust surfaces', 'Vacuum carpet', 'Disinfect handles', 'Clean windows'],
            'Gym'             => ['Disinfect equipment', 'Vacuum carpet', 'Open windows for airing', 'Clean baseboards'],
            'Pool Area'       => ['Skim pool surface', 'Sweep balcony', 'Empty trash'],
            'Garden'          => ['Water plants', 'Sweep balcony'],
            'Game Room'       => ['Dust surfaces', 'Vacuum carpet', 'Vacuum upholstery'],
        ];

        // ---- Create Properties, assign to Owners, attach Rooms (pivot), attach Tasks (pivot) ----
        $allProperties = collect();

        for ($i = 0; $i < $propertyCount; $i++) {
            $ownerUser = $owners->random();

            /** @var Property $property */
            $property = Property::factory()->create([
                'owner_id' => $ownerUser->id, // <-- Assign property to owner
            ]);

            // Pick N unique rooms from catalog
            $requestedRooms = fake()->numberBetween(...$roomsPerProperty);
            $chosenRooms = $roomsCatalog->shuffle()->take($requestedRooms)->values();

            // Attach rooms with per-property sort order on pivot
            $roomPivotData = [];
            foreach ($chosenRooms as $idx => $room) {
                $roomPivotData[$room->id] = ['sort_order' => $idx + 1];
            }
            $property->rooms()->syncWithoutDetaching($roomPivotData);

            // For each chosen room: attach tasks w/ per-room sort_order + instructions
            foreach ($chosenRooms as $room) {
                $preferred = $roomTaskMap[$room->name] ?? $tasksCatalog->pluck('name')->all();
                $taskSet = $tasksCatalog->whereIn('name', $preferred);

                $pickCount = fake()->numberBetween(...$tasksPerRoom);
                $attachTasks = $taskSet->shuffle()->take($pickCount)->values();

                $taskPivot = [];
                foreach ($attachTasks as $k => $task) {
                    $taskPivot[$task->id] = [
                        'sort_order'             => $k + 1,
                        'instructions'           => $this->roomSpecificInstructions($room->name, $task->name),
                        'visible_to_owner'       => true,
                        'visible_to_housekeeper' => true,
                    ];
                }
                $room->tasks()->syncWithoutDetaching($taskPivot);
            }

            $allProperties->push($property);
        }

        // ---- Cleaning Sessions: assign each to a Housekeeper ----
        // Unique per (hk, property, date)
        $usedKey = [];
        $statusOptions = ['pending', 'in_progress', 'completed'];

        foreach ($allProperties as $property) {
            $sessionCount = fake()->numberBetween(...$sessionsPerProp);

            for ($s = 0; $s < $sessionCount; $s++) {
                $hkUser = $housekeepers->random();               // <-- Assign session to housekeeper
                $date   = fake()->dateTimeBetween('-10 days', '+20 days')->format('Y-m-d');
                $key    = "{$hkUser->id}-{$property->id}-{$date}";

                if (isset($usedKey[$key])) {
                    $s--;
                    continue;
                }
                $usedKey[$key] = true;

                $status = Arr::random($statusOptions);

                /** @var CleaningSession $session */
                $session = CleaningSession::factory()->create([
                    'property_id'     => $property->id,
                    'owner_id'        => $property->owner_id,
                    'housekeeper_id'  => $hkUser->id,
                    'scheduled_date'  => $date,
                    'status'          => $status,
                    'start_latitude'  => $property->latitude ? ($property->latitude + fake()->randomFloat(6, -0.0008, 0.0008)) : null,
                    'start_longitude' => $property->longitude ? ($property->longitude + fake()->randomFloat(6, -0.0008, 0.0008)) : null,
                ]);

                // Build checklist from room_task pivot for this property:
                // 1) Rooms for this property (via pivot)
                $propertyRooms = $property->rooms()->get();

                // 2) For each room, tasks via pivot
                foreach ($propertyRooms as $room) {
                    $roomTasks = $room->tasks()->orderBy('room_task.sort_order')->get();

                    foreach ($roomTasks as $task) {
                        ChecklistItem::factory()->create([
                            'session_id' => $session->id,
                            'room_id'    => $room->id,     // store room reference (even though room<>property is via pivot)
                            'task_id'    => $task->id,
                            'user_id'    => $hkUser->id,
                            'checked'    => $status === 'completed' ? true : fake()->boolean(60),
                            'checked_at' => $status === 'completed' ? now() : (fake()->boolean(50) ? now() : null),
                            'note'       => fake()->optional(0.15)->sentence(10),
                        ]);
                    }
                }

                // Room photos
                $minPhotos = $status === 'completed' ? 8 : fake()->numberBetween(2, 6);
                foreach ($propertyRooms as $room) {
                    for ($i = 0; $i < $minPhotos; $i++) {
                        RoomPhoto::factory()->create([
                            'session_id'  => $session->id,
                            'room_id'     => $room->id,
                            'captured_at' => now()->subMinutes(fake()->numberBetween(0, 180)),
                        ]);
                    }
                }
            }
        }
    }

    private function roomSpecificInstructions(string $roomName, string $taskName): string
    {
        $tips = [
            'Wipe counters'           => 'Use disinfectant; wipe top → bottom.',
            'Clean sink'              => 'Non-abrasive cleaner; rinse & dry.',
            'Mop floor'               => 'Start far corner; exit last.',
            'Clean stovetop'          => 'Degreaser on cool surface.',
            'Empty trash'             => 'Tie bag; replace liner.',
            'Make bed'                => 'Tight corners; smooth duvet.',
            'Change linens'           => 'Use fresh set; tuck tightly.',
            'Dust surfaces'           => 'Microfiber; top → bottom.',
            'Vacuum carpet'           => 'Slow passes; edges then center.',
            'Scrub toilet'            => 'Disinfect rim, seat, hinges.',
            'Clean mirror'            => 'Circular motion; no streaks.',
            'Wipe shower walls'       => 'Squeegee after cleaning.',
            'Refill toiletries'       => 'Stock to visible levels.',
            'Check inventory'         => 'Record low items; reorder.',
            'Disinfect handles'       => 'Door/appliance/cabinet handles.',
            'Open windows for airing' => 'Air for 5–10 minutes.',
        ];

        $roomNotes = [
            'Kitchen'  => 'Focus on food-contact surfaces.',
            'Bathroom' => 'Wear gloves; sanitize high-touch areas.',
            'Bedroom'  => 'Arrange guest items neatly.',
        ];

        $base = $tips[$taskName] ?? 'Follow SOP.';
        $extra = $roomNotes[$roomName] ?? null;

        return $extra ? "{$base} {$extra}" : $base;
    }
}
