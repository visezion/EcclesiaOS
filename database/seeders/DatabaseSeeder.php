<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AttendanceVerification;
use App\Models\BookstoreOrder;
use App\Models\BookstoreProduct;
use App\Models\Campus;
use App\Models\CareTask;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Family;
use App\Models\Feedback;
use App\Models\Fund;
use App\Models\MeetingIntegration;
use App\Models\Member;
use App\Models\Ministry;
use App\Models\Permission;
use App\Models\Program;
use App\Models\PrayerRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\Volunteer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $church = $this->seedChurch();
        $campuses = $this->seedCampuses($church);
        $roles = $this->seedAccessControl();
        $users = $this->seedUsers($church, $campuses, $roles);
        $members = $this->seedMembers($church, $campuses);
        $this->seedFamilies($church, $members);
        $funds = $this->seedFunds($church);

        $this->seedEvents($church, $campuses);
        $this->seedProgramEventFlow($church, $campuses, $members);
        $this->seedAttendance($church, $campuses, $members);
        $this->seedDonations($church, $campuses, $members, $funds);
        $this->seedMinistriesAndVolunteers($church, $campuses, $members);
        $this->seedAssets($church, $campuses);
        $this->seedBookstore($church, $campuses);
        $this->seedFeedbackAndPrayer($church, $campuses, $members);
        $this->seedCareTasks($church, $members, $users);
        $this->seedActivityLogs($church, $campuses, $users);
        $this->call(CommunicationDemoSeeder::class);
    }

    private function seedChurch(): Church
    {
        return Church::query()->updateOrCreate(
            ['slug' => 'kingdom-life-global-church'],
            [
                'name' => config('church.name'),
                'timezone' => config('church.timezone'),
                'currency' => config('church.currency'),
                'email' => config('church.contact_email'),
                'phone' => config('church.contact_phone'),
                'address' => '123 Kingdom Way, Dallas, TX 75201, USA',
                'settings' => ['branding' => ['primary' => '#6d4aff']],
            ],
        );
    }

    private function seedCampuses(Church $church): array
    {
        $rows = [
            ['Headquarters', 'headquarters', 'Main Campus', 'Dallas', 'USA', 'Active', 42, 64, 2200],
            ['North Campus', 'north-campus', 'Regional Campus', 'Plano', 'USA', 'Active', 48, 57, 900],
            ['South Campus', 'south-campus', 'Regional Campus', 'Houston', 'USA', 'Active', 50, 70, 760],
            ['West Campus', 'west-campus', 'Regional Campus', 'Fort Worth', 'USA', 'Active', 37, 65, 820],
            ['East Campus', 'east-campus', 'Regional Campus', 'Garland', 'USA', 'Active', 57, 62, 620],
            ['Downtown Campus', 'downtown-campus', 'City Campus', 'Dallas', 'USA', 'Active', 44, 66, 480],
            ['Online Campus', 'online-campus', 'Online Campus', 'Online', 'Global', 'Active', 68, 45, null],
            ['Youth Network', 'youth-network', 'Ministry Campus', 'Dallas', 'USA', 'Inactive', 46, 68, 320],
        ];

        return collect($rows)->mapWithKeys(function (array $row) use ($church): array {
            [$name, $slug, $type, $city, $country, $status, $x, $y, $capacity] = $row;

            return [$slug => Campus::query()->updateOrCreate(
                ['church_id' => $church->id, 'slug' => $slug],
                [
                    'name' => $name,
                    'type' => $type,
                    'city' => $city,
                    'country' => $country,
                    'address' => $city === 'Online' ? 'Online' : $city.', TX',
                    'capacity' => $capacity,
                    'map_x' => $x,
                    'map_y' => $y,
                    'status' => strtolower($status),
                    'metadata' => [
                        'service_location' => $slug === 'headquarters' ? 'Main Sanctuary' : $name.' Auditorium',
                        'sunday_service' => $slug === 'online-campus' ? 'Streaming Campus' : '9:00 AM',
                    ],
                ],
            )];
        })->all();
    }

    private function seedAccessControl(): array
    {
        $permissions = collect(config('access.permissions'))
            ->unique()
            ->mapWithKeys(fn (string $permission): array => [
                $permission => Permission::query()->updateOrCreate(
                    ['slug' => Str::slug($permission)],
                    ['name' => $permission, 'description' => 'Allows user to '.$permission],
                ),
            ]);

        Role::query()->whereNotIn('name', array_keys(config('access.roles')))->delete();

        return collect(config('access.roles'))->mapWithKeys(function (array $rolePermissions, string $roleName) use ($permissions): array {
            $role = Role::query()->updateOrCreate(
                ['slug' => Str::slug($roleName)],
                ['name' => $roleName, 'description' => $roleName.' application role'],
            );

            $role->permissions()->sync(
                $rolePermissions === ['*']
                    ? $permissions->pluck('id')->all()
                    : $permissions->only($rolePermissions)->pluck('id')->all(),
            );

            return [$roleName => $role];
        })->all();
    }

    private function seedUsers(Church $church, array $campuses, array $roles): array
    {
        $rows = [
            ['Pastor John', 'Senior Pastor', 'admin@kingdomhub.test', 'EMP-00125', 'Super Administrator', 'headquarters', '+1 (555) 012-3456', 'active'],
            ['Sarah Johnson', 'Church Administrator', 'sarah.johnson@klgc.org', 'EMP-00126', 'Church Administrator', 'west-campus', '+1 (555) 234-5678', 'active'],
            ['Michael Thompson', 'Finance Officer', 'michael.thompson@klgc.org', 'EMP-00127', 'Finance Officer', 'downtown-campus', '+1 (555) 345-6789', 'active'],
            ['Emily Davis', 'Ministry Leader', 'emily.davis@klgc.org', 'EMP-00128', 'Ministry Leader', 'north-campus', '+1 (555) 456-7890', 'active'],
            ['David Wilson', 'Branch Pastor', 'david.wilson@klgc.org', 'EMP-00129', 'Branch Pastor', 'south-campus', '+1 (555) 567-8901', 'active'],
            ['Lisa Martinez', 'Asset Manager', 'lisa.martinez@klgc.org', 'EMP-00130', 'Asset Manager', 'east-campus', '+1 (555) 678-9012', 'active'],
            ['James Anderson', 'Book Store Manager', 'james.anderson@klgc.org', 'EMP-00131', 'Book Store Manager', 'west-campus', '+1 (555) 789-0123', 'suspended'],
            ['Amanda Brown', 'Membership Officer', 'amanda.brown@klgc.org', 'EMP-00132', 'Membership Officer', 'youth-network', '+1 (555) 890-1234', 'active'],
            ['Robert Taylor', 'Staff', 'robert.taylor@klgc.org', 'EMP-00133', 'Staff', 'downtown-campus', '+1 (555) 901-2345', 'active'],
            ['Jessica Lee', 'Viewer', 'jessica.lee@klgc.org', 'EMP-00134', 'Viewer', 'north-campus', '+1 (555) 012-3456', 'active'],
        ];

        return collect($rows)->mapWithKeys(function (array $row, int $index) use ($church, $campuses, $roles): array {
            [$name, $title, $email, $employeeId, $roleName, $campusSlug, $phone, $status] = $row;
            $avatarPath = $this->seedAvatar($name, $email, $index);

            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'church_id' => $church->id,
                    'campus_id' => $campuses[$campusSlug]->id,
                    'name' => $name,
                    'title' => $title,
                    'phone' => $phone,
                    'employee_id' => $employeeId,
                    'date_joined' => Carbon::parse('2018-01-15')->addDays($index * 31),
                    'date_of_birth' => Carbon::parse('1978-03-12'),
                    'gender' => str_contains($name, 'Sarah') || str_contains($name, 'Emily') || str_contains($name, 'Lisa') || str_contains($name, 'Amanda') || str_contains($name, 'Jessica') ? 'Female' : 'Male',
                    'address' => '123 Kingdom Way, Dallas, TX 75201, USA',
                    'timezone' => '(UTC-06:00) Central Time (US & Canada)',
                    'emergency_contact_name' => 'Sarah Johnson',
                    'emergency_contact_relationship' => 'Spouse',
                    'emergency_contact_phone' => '+1 (555) 987-6543',
                    'recovery_email' => Str::before($email, '@').'.recovery@klgc.org',
                    'mfa_enabled' => true,
                    'avatar_url' => $avatarPath,
                    'status' => $status,
                    'password' => Hash::make('password'),
                    'password_changed_at' => now()->subDays(28),
                    'last_login_at' => now()->subHours(rand(2, 72)),
                ],
            );

            $user->roles()->sync([$roles[$roleName]->id]);

            return [$email => $user];
        })->all();
    }

    private function seedAvatar(string $name, string $email, int $index): string
    {
        $path = 'avatars/seeded/'.Str::slug($email).'.svg';
        $palette = [
            ['#6d4aff', '#2477f2'],
            ['#10b981', '#14b8a6'],
            ['#f97316', '#f59e0b'],
            ['#f43f5e', '#6d4aff'],
            ['#2477f2', '#14b8a6'],
        ];
        [$from, $to] = $palette[$index % count($palette)];
        $initials = Str::of($name)->explode(' ')->map(fn (string $part): string => Str::substr($part, 0, 1))->take(2)->join('');
        $safeInitials = htmlspecialchars(Str::upper($initials), ENT_QUOTES, 'UTF-8');

        Storage::disk('public')->put($path, <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="160" height="160" viewBox="0 0 160 160" role="img" aria-label="{$safeInitials}">
    <defs>
        <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="{$from}"/>
            <stop offset="1" stop-color="{$to}"/>
        </linearGradient>
    </defs>
    <rect width="160" height="160" rx="80" fill="url(#g)"/>
    <circle cx="80" cy="58" r="28" fill="#ffffff" opacity=".88"/>
    <path d="M35 136c7-30 25-45 45-45s38 15 45 45" fill="#ffffff" opacity=".82"/>
    <text x="80" y="95" text-anchor="middle" font-family="Inter, Arial, sans-serif" font-size="36" font-weight="800" fill="#0f172a">{$safeInitials}</text>
</svg>
SVG);

        return $path;
    }

    private function seedMembers(Church $church, array $campuses): array
    {
        $names = [
            'Sarah Johnson', 'Michael Thompson', 'Mary Johnson', 'David Wilson', 'Lisa Martinez', 'James Anderson', 'Amanda Brown', 'Robert Taylor',
            'Jessica Lee', 'Chris Walker', 'Daniel Harris', 'Rachel Green', 'Kevin White', 'Grace Miller', 'Samuel Clark', 'Naomi Hill',
            'Victor Adams', 'Faith Roberts', 'Joshua Carter', 'Hope Evans', 'Brian Nelson', 'Ruth Turner', 'Caleb Reed', 'Joy Morgan',
        ];

        $campusList = array_values($campuses);

        return collect($names)->mapWithKeys(function (string $name, int $index) use ($church, $campusList): array {
            [$first, $last] = explode(' ', $name);
            $member = Member::query()->updateOrCreate(
                ['email' => Str::slug($name, '.').'@members.klgc.org'],
                [
                    'church_id' => $church->id,
                    'campus_id' => $campusList[$index % count($campusList)]->id,
                    'first_name' => $first,
                    'last_name' => $last,
                    'phone' => '+1 (555) '.str_pad((string) (100 + $index), 3, '0', STR_PAD_LEFT).'-'.str_pad((string) (2000 + $index), 4, '0', STR_PAD_LEFT),
                    'status' => 'active',
                    'joined_at' => now()->subDays(500 - $index),
                ],
            );

            return [$member->email => $member];
        })->all();
    }

    private function seedFunds(Church $church): array
    {
        return collect(['Tithes', 'Offerings', 'Building Fund', 'Missions', 'Other'])->mapWithKeys(fn (string $name): array => [
            $name => Fund::query()->updateOrCreate(
                ['church_id' => $church->id, 'name' => $name],
                ['code' => Str::upper(Str::slug($name, '_')), 'description' => $name.' fund', 'is_active' => true],
            ),
        ])->all();
    }

    private function seedFamilies(Church $church, array $members): void
    {
        foreach (array_chunk(array_values($members), 4) as $index => $familyMembers) {
            $head = $familyMembers[0];
            $family = Family::query()->updateOrCreate(
                ['church_id' => $church->id, 'name' => $head->last_name.' Family'],
                [
                    'campus_id' => $head->campus_id,
                    'primary_contact_id' => $head->id,
                    'address' => ($index + 100).' Kingdom Way, Dallas, TX',
                ],
            );

            Member::query()->whereIn('id', collect($familyMembers)->pluck('id'))->update(['family_id' => $family->id]);
        }
    }

    private function seedEvents(Church $church, array $campuses): void
    {
        $rows = [
            ['Sunday Worship Service', 'Service', 'Main Sanctuary', '2026-07-26 09:00:00'],
            ['Youth Night', 'Youth', 'Youth Center', '2026-07-29 19:00:00'],
            ['Communion Sunday', 'Service', 'Main Sanctuary', '2026-08-02 09:00:00'],
            ["Women's Fellowship", 'Fellowship', 'Fellowship Hall', '2026-08-07 18:00:00'],
            ['Youth Camp 2026', 'Event', 'Camp Glory', '2026-08-15 08:00:00'],
        ];

        foreach ($rows as $index => [$title, $category, $venue, $startsAt]) {
            Event::query()->updateOrCreate(
                ['church_id' => $church->id, 'title' => $title],
                [
                    'campus_id' => array_values($campuses)[$index % count($campuses)]->id,
                    'starts_at' => Carbon::parse($startsAt),
                    'ends_at' => Carbon::parse($startsAt)->addHours(2),
                    'venue' => $venue,
                    'category' => $category,
                    'status' => 'scheduled',
                ],
            );
        }
    }

    private function seedProgramEventFlow(Church $church, array $campuses, array $members): void
    {
        foreach (['zoom', 'google_meet', 'jitsi', 'livekit'] as $provider) {
            MeetingIntegration::query()->updateOrCreate(
                ['church_id' => $church->id, 'provider' => $provider],
                [
                    'enabled' => true,
                    'settings' => [
                        'internal_endpoint' => '/meetings',
                        'webhook_secret_hash' => hash('sha256', 'seeded-secret'),
                        'webhook_secret_configured' => true,
                        'webhook_event' => 'internal.participant_joined',
                        'room_prefix' => 'kingdomlife',
                        'identity_field' => 'email',
                        'recording_retention_days' => 30,
                        'last_test_status' => 'healthy',
                        'last_test_message' => 'Built-in meeting adapter is ready inside EcclesiaOS.',
                    ],
                    'last_tested_at' => now()->subHours(3),
                ],
            );
        }

        $programRows = [
            ['Youth Camp 2026', 'Annual youth camp for teens with worship, teaching, and activities.', '2026-07-10', '2026-07-12', 'upcoming', 'youth-network'],
            ['Leadership Training', 'Training for leaders and volunteers.', '2026-08-01', '2026-08-03', 'upcoming', 'headquarters'],
            ['Marriage Retreat', 'Couples retreat and enrichment.', '2026-09-15', '2026-09-17', 'upcoming', 'north-campus'],
        ];

        foreach ($programRows as [$name, $description, $startsOn, $endsOn, $status, $campusSlug]) {
            $program = Program::query()->updateOrCreate(
                ['church_id' => $church->id, 'name' => $name],
                [
                    'campus_id' => $campuses[$campusSlug]->id,
                    'description' => $description,
                    'starts_on' => $startsOn,
                    'ends_on' => $endsOn,
                    'status' => $status,
                ],
            );

            foreach ([
                ['Opening Service', 'Kick-off service for the program.', 'Service', '2026-07-10 09:00:00', 'Main Auditorium', 'hybrid'],
                ['Worship Night', 'Evening worship and prayer.', 'Service', '2026-07-11 19:00:00', 'Main Auditorium', 'physical'],
                ['Leadership Workshop', 'Leadership and character training.', 'Workshop', '2026-07-12 10:00:00', 'Training Hall', 'online'],
            ] as [$title, $eventDescription, $type, $startsAt, $venue, $meetingType]) {
                $event = Event::query()->updateOrCreate(
                    ['church_id' => $church->id, 'program_id' => $program->id, 'title' => $title],
                    [
                        'campus_id' => $program->campus_id,
                        'description' => $eventDescription,
                        'event_type' => $type,
                        'starts_at' => Carbon::parse($startsAt),
                        'ends_at' => Carbon::parse($startsAt)->addMinutes(90),
                        'venue' => $venue,
                        'category' => $type,
                        'status' => 'scheduled',
                    ],
                );

                $session = EventSession::query()->updateOrCreate(
                    ['event_id' => $event->id, 'title' => $title.' - '.Carbon::parse($startsAt)->format('l')],
                    [
                        'church_id' => $church->id,
                        'campus_id' => $program->campus_id,
                        'session_date' => Carbon::parse($startsAt)->toDateString(),
                        'starts_at' => Carbon::parse($startsAt)->format('H:i:s'),
                        'ends_at' => Carbon::parse($startsAt)->addMinutes(90)->format('H:i:s'),
                        'timezone' => $church->timezone,
                        'meeting_type' => $meetingType,
                        'venue' => $venue,
                        'address' => $campuses[$campusSlug]->address,
                        'capacity' => 320,
                        'status' => 'scheduled',
                        'meeting_links' => [
                            'zoom' => ['room' => 'kingdomlife-zoom-'.$event->id, 'access_code' => 'KLCG-'.$event->id],
                            'google_meet' => ['room' => 'kingdomlife-meet-'.$event->id, 'access_code' => 'KLCG-'.$event->id],
                            'jitsi' => ['room' => 'kingdomlife-jitsi-'.$event->id, 'access_code' => 'KLCG-'.$event->id],
                            'livekit' => ['room' => 'kingdomlife-livekit-'.$event->id, 'access_code' => 'KLCG-'.$event->id],
                        ],
                    ],
                );

                $methods = match ($meetingType) {
                    'online' => ['zoom', 'google_meet', 'jitsi', 'livekit'],
                    'hybrid' => ['manual', 'qr', 'geolocation', 'kiosk', 'face', 'zoom', 'google_meet', 'jitsi', 'livekit'],
                    default => ['manual', 'qr', 'geolocation', 'kiosk', 'face'],
                };

                $attendanceSession = AttendanceSession::query()->updateOrCreate(
                    ['event_session_id' => $session->id],
                    [
                        'church_id' => $church->id,
                        'campus_id' => $program->campus_id,
                        'title' => $session->title.' Attendance',
                        'opens_at' => Carbon::parse($startsAt)->subMinutes(30),
                        'closes_at' => Carbon::parse($startsAt)->addMinutes(105),
                        'methods' => $methods,
                        'verification_policy' => 'any_one',
                        'require_authenticated' => true,
                        'allow_guests' => false,
                        'geo_latitude' => 32.7767000,
                        'geo_longitude' => -96.7970000,
                        'geo_radius_meters' => 100,
                        'expected_attendance' => 320,
                        'status' => 'scheduled',
                    ],
                );

                foreach (array_slice(array_values($members), 0, 4) as $index => $member) {
                    $record = AttendanceRecord::query()->updateOrCreate(
                        ['attendance_session_id' => $attendanceSession->id, 'member_id' => $member->id],
                        [
                            'church_id' => $church->id,
                            'campus_id' => $program->campus_id,
                            'event_id' => $event->id,
                            'service_date' => Carbon::parse($startsAt)->toDateString(),
                            'status' => 'present',
                            'final_method' => $index % 2 === 0 ? 'qr' : 'zoom',
                            'checked_in_at' => Carbon::parse($startsAt)->addMinutes(10 + $index),
                            'verification_summary' => [],
                            'metadata' => ['source' => 'seeded event attendance'],
                        ],
                    );

                    AttendanceVerification::query()->updateOrCreate(
                        ['attendance_session_id' => $attendanceSession->id, 'attendance_record_id' => $record->id, 'member_id' => $member->id, 'method' => $record->final_method],
                        ['provider' => $record->final_method, 'status' => 'success', 'confidence' => $record->final_method === 'qr' ? 90 : 88, 'verified_at' => $record->checked_in_at, 'metadata' => ['seeded' => true]],
                    );
                }
            }
        }
    }

    private function seedAttendance(Church $church, array $campuses, array $members): void
    {
        $months = collect(range(5, 0))->map(fn (int $i): Carbon => now()->subMonths($i)->startOfMonth()->addDays(6));

        foreach ($months as $monthIndex => $date) {
            foreach (array_values($campuses) as $campusIndex => $campus) {
                $count = 160 + ($monthIndex * 22) + ($campusIndex * 7);
                foreach (array_slice(array_values($members), 0, min($count, count($members))) as $member) {
                    AttendanceRecord::query()->updateOrCreate(
                        ['church_id' => $church->id, 'campus_id' => $campus->id, 'member_id' => $member->id, 'service_date' => $date->toDateString()],
                        ['status' => 'present', 'checked_in_at' => $date->copy()->setTime(8, 45), 'metadata' => ['source' => 'seeded check-in']],
                    );
                }
            }
        }
    }

    private function seedDonations(Church $church, array $campuses, array $members, array $funds): void
    {
        foreach (array_values($funds) as $fundIndex => $fund) {
            foreach (range(1, 12) as $i) {
                $member = array_values($members)[($i + $fundIndex) % count($members)];
                Donation::query()->updateOrCreate(
                    ['reference' => 'DEMO-'.$fund->id.'-'.$i],
                    [
                        'church_id' => $church->id,
                        'campus_id' => array_values($campuses)[$i % count($campuses)]->id,
                        'member_id' => $member->id,
                        'fund_id' => $fund->id,
                        'amount' => [5200, 2700, 1650, 730, 440][$fundIndex] + ($i * 13),
                        'currency' => 'USD',
                        'method' => ['card', 'bank', 'cash'][$i % 3],
                        'received_at' => now()->subDays($i * 2),
                    ],
                );
            }
        }
    }

    private function seedMinistriesAndVolunteers(Church $church, array $campuses, array $members): void
    {
        foreach (['Worship Ministry', "Children's Ministry", 'Youth Ministry', 'Outreach Ministry', 'Prayer Ministry'] as $index => $name) {
            $ministry = Ministry::query()->updateOrCreate(
                ['church_id' => $church->id, 'name' => $name],
                [
                    'campus_id' => array_values($campuses)[$index % count($campuses)]->id,
                    'leader_id' => array_values($members)[$index]->id,
                    'description' => $name.' operations and member care.',
                    'status' => 'active',
                ],
            );

            foreach (array_slice(array_values($members), $index, 6) as $member) {
                Volunteer::query()->updateOrCreate(
                    ['church_id' => $church->id, 'member_id' => $member->id, 'ministry_id' => $ministry->id],
                    [
                        'campus_id' => $member->campus_id,
                        'role' => 'Team Member',
                        'status' => 'active',
                        'availability' => ['sunday' => true],
                    ],
                );
            }
        }
    }

    private function seedAssets(Church $church, array $campuses): void
    {
        $rows = [
            ['Chairs', 1250, 'good'], ['Microphones', 48, 'good'], ['Cameras', 16, 'fair'], ['Projectors', 14, 'maintenance'],
            ['Laptops', 32, 'good'], ['Musical Instruments', 29, 'fair'], ['Vehicles', 7, 'good'], ['Generators', 4, 'maintenance'],
        ];

        foreach ($rows as [$categoryName, $count, $condition]) {
            $category = AssetCategory::query()->updateOrCreate(
                ['church_id' => $church->id, 'name' => $categoryName],
                ['description' => $categoryName.' inventory category'],
            );

            foreach (range(1, min((int) $count, 25)) as $i) {
                Asset::query()->updateOrCreate(
                    ['serial_number' => Str::upper(Str::slug($categoryName)).'-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT)],
                    [
                        'church_id' => $church->id,
                        'campus_id' => array_values($campuses)[$i % count($campuses)]->id,
                        'asset_category_id' => $category->id,
                        'name' => Str::singular($categoryName).' '.$i,
                        'status' => $condition === 'maintenance' && $i % 3 === 0 ? 'maintenance' : 'available',
                        'condition' => $condition,
                        'purchased_at' => now()->subMonths($i),
                        'purchase_amount' => 100 + ($i * 12),
                    ],
                );
            }
        }
    }

    private function seedBookstore(Church $church, array $campuses): void
    {
        $products = [
            ['Destined for Impact', 'Books', 10.00, 122],
            ['Walking in Faith', 'Books', 10.00, 98],
            ['Prayers that Avail Much', 'Books', 10.00, 85],
            ['Grace for Today', 'Books', 10.00, 74],
            ['Victory in Worship', 'Music', 10.00, 65],
        ];

        foreach ($products as $index => [$name, $category, $price, $stock]) {
            BookstoreProduct::query()->updateOrCreate(
                ['church_id' => $church->id, 'sku' => 'BOOK-'.$index],
                [
                    'campus_id' => array_values($campuses)[$index % count($campuses)]->id,
                    'name' => $name,
                    'category' => $category,
                    'price' => $price,
                    'stock_quantity' => $stock,
                    'reorder_level' => 20,
                    'status' => 'active',
                ],
            );
        }

        foreach (range(1, 32) as $i) {
            BookstoreOrder::query()->updateOrCreate(
                ['order_number' => 'KH-ORDER-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT)],
                [
                    'church_id' => $church->id,
                    'campus_id' => array_values($campuses)[$i % count($campuses)]->id,
                    'member_id' => null,
                    'total_amount' => 180 + ($i * 7),
                    'currency' => 'USD',
                    'status' => 'paid',
                    'ordered_at' => now()->subDays($i),
                ],
            );
        }
    }

    private function seedFeedbackAndPrayer(Church $church, array $campuses, array $members): void
    {
        foreach (range(1, 18) as $i) {
            $member = array_values($members)[$i % count($members)];
            Feedback::query()->updateOrCreate(
                ['church_id' => $church->id, 'subject' => 'Feedback item '.$i],
                [
                    'campus_id' => $member->campus_id,
                    'member_id' => $member->id,
                    'type' => ['suggestion', 'complaint', 'praise'][$i % 3],
                    'message' => 'Member feedback recorded from the portal.',
                    'sentiment' => ['positive', 'neutral', 'negative'][$i % 3],
                    'status' => $i % 4 === 0 ? 'open' : 'resolved',
                    'resolved_at' => $i % 4 === 0 ? null : now()->subDays($i),
                ],
            );

            PrayerRequest::query()->updateOrCreate(
                ['church_id' => $church->id, 'title' => 'Prayer request '.$i],
                [
                    'campus_id' => $member->campus_id,
                    'member_id' => $member->id,
                    'request' => 'Prayer support requested by '.$member->first_name.'.',
                    'status' => $i % 3 === 0 ? 'open' : 'followed-up',
                    'is_confidential' => $i % 5 === 0,
                    'followed_up_at' => $i % 3 === 0 ? null : now()->subDays($i),
                ],
            );
        }
    }

    private function seedCareTasks(Church $church, array $members, array $users): void
    {
        $types = ['Counseling', 'Visitation', 'Prayer Request', 'Membership', 'Family Care', 'Hospital Visit'];
        $statuses = ['pending', 'assigned', 'in-progress', 'on-hold', 'resolved'];
        $priorities = ['low', 'medium', 'high', 'urgent'];

        foreach (array_slice(array_values($members), 0, 14) as $index => $member) {
            $status = $statuses[$index % count($statuses)];
            CareTask::query()->updateOrCreate(
                ['church_id' => $church->id, 'member_id' => $member->id, 'type' => $types[$index % count($types)]],
                [
                    'campus_id' => $member->campus_id,
                    'assigned_user_id' => array_values($users)[$index % count($users)]->id,
                    'priority' => $priorities[$index % count($priorities)],
                    'status' => $status,
                    'next_action' => ['Schedule counseling session', 'Follow up by phone', 'Hospital visit follow-up', 'Send encouragement', 'Review membership path'][$index % 5],
                    'notes' => 'Pastoral care task recorded for '.$member->first_name.' '.$member->last_name.'.',
                    'due_at' => now()->addDays($index + 1)->setTime(10, 0),
                    'resolved_at' => $status === 'resolved' ? now()->subDays($index) : null,
                ],
            );
        }
    }

    private function seedActivityLogs(Church $church, array $campuses, array $users): void
    {
        $actions = [
            ['Authentication', 'login', 'Successful login', 'low', 'success'],
            ['Authentication', 'logout', 'User logged out', 'low', 'success'],
            ['Authentication', 'password_reset_completed', 'Password changed', 'medium', 'success'],
            ['Access Control', 'role_permissions_updated', 'Permissions updated', 'medium', 'success'],
            ['Access Control', 'user_created', 'User account created', 'medium', 'success'],
            ['Authentication', 'failed_login', 'Invalid credentials', 'high', 'failed'],
            ['Access Control', 'mfa_enabled', 'MFA activated', 'low', 'success'],
            ['Access Control', 'role_assigned', 'Role assigned', 'medium', 'success'],
            ['Access Policy', 'policy_updated', 'Policy configuration updated', 'low', 'success'],
            ['Authentication', 'failed_login', 'Invalid token', 'high', 'failed'],
        ];

        foreach ($actions as $index => [$module, $action, $description, $risk, $status]) {
            $user = array_values($users)[$index % count($users)];
            ActivityLog::query()->updateOrCreate(
                ['module' => $module, 'action' => $action, 'description' => $description],
                [
                    'church_id' => $church->id,
                    'campus_id' => array_values($campuses)[$index % count($campuses)]->id,
                    'user_id' => $status === 'failed' ? null : $user->id,
                    'subject_type' => $status === 'failed' ? null : $user->getMorphClass(),
                    'subject_id' => $status === 'failed' ? null : $user->id,
                    'ip_address' => $status === 'failed' ? '203.0.113.'.(20 + $index) : '192.168.1.45',
                    'user_agent' => 'Chrome on macOS',
                    'properties' => ['risk' => $risk, 'status' => $status, 'resource' => $module === 'Access Control' ? 'User Account' : 'Web Portal'],
                ],
            );
        }
    }
}
