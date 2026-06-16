<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use App\Models\CleaningSession;
use App\Models\Property;
use App\Models\PropertyCheckout;
use App\Models\Room;
use App\Models\User;
use App\Services\ICalService;

class DashboardController extends Controller
{
    private $icalService;

    public function __construct(ICalService $icalService)
    {
        $this->icalService = $icalService;
    }
    /** Resolve acting role (admin wins unless ?as=owner and user also has owner). */
    private function actingRole(Request $request): string
    {
        $u = $request->user();
        $isAdmin = $u?->hasRole('admin') ?? false;
        $isOwner = $u?->hasRole('owner') ?? false;
        $isCompany = $u?->hasRole('company') ?? false;
        $isHK    = $u?->hasRole('housekeeper') ?? false;

        if ($isAdmin) {
            if (($isOwner || $isCompany) && $request->query('as') === 'owner') return 'owner';
            return 'admin';
        }
        if ($isOwner || $isCompany) return 'owner';
        if ($isHK) return 'housekeeper';

        // Final fallback: if no role is found, treat as housekeeper to be safe
        return 'housekeeper';
    }

    /** Base scoped sessions query by acting role. */
    private function baseSessions(string $acting, int $userId)
    {
        $q = CleaningSession::query()->with(['property:id,name,owner_id', 'housekeeper:id,name']);

        if ($acting === 'housekeeper') {
            $q->where('housekeeper_id', $userId);
        } elseif ($acting === 'owner') {
            $q->whereHas('property', fn($p) => $p->where('owner_id', $userId));
        } elseif ($acting === 'company') {
            // Company sees properties they own OR properties owned by people they manage OR properties explicitly assigned
            $q->whereHas('property', function ($p) use ($userId) {
                $p->where('owner_id', $userId)
                  ->orWhereIn('owner_id', function ($sub) use ($userId) {
                      $sub->select('owner_id')
                          ->from('housekeeper_owner')
                          ->where('housekeeper_id', $userId);
                  })
                  ->orWhereIn('owner_id', function ($sub) use ($userId) {
                      // Also support the direct user-to-owner relationship if used
                      $sub->select('id')
                          ->from('users')
                          ->where('owner_id', $userId);
                  })
                  ->orWhereHas('users', function ($sub2) use ($userId) {
                      $sub2->where('users.id', $userId);
                  });
            });
        }

        // admin: no scope
        return $q;
    }

    /** Visible property ids for counts/lists. */
    private function visiblePropertyIds(string $acting, int $userId)
    {
        if ($acting === 'admin') {
            // null => unscoped (use carefully)
            return null;
        }
        if ($acting === 'owner') {
            $user = User::find($userId);
            if ($user && $user->hasRole('company')) {
                return Property::where(function($q) use ($userId) {
                    $q->where('owner_id', $userId)
                      ->orWhereIn('owner_id', function($sub) use ($userId) {
                          $sub->select('id')->from('users')->where('owner_id', $userId);
                      })
                      ->orWhereIn('owner_id', function($sub) use ($userId) {
                          $sub->select('owner_id')
                              ->from('housekeeper_owner')
                              ->where('housekeeper_id', $userId);
                      })
                      ->orWhereHas('users', function($sub2) use ($userId) {
                          $sub2->where('users.id', $userId);
                      });
                })->pluck('id');
            }
            return Property::where('owner_id', $userId)->pluck('id');
        }
        // housekeeper: distinct properties from their sessions (last 180d + next 180d for practicality)
        $from = Carbon::now()->subDays(180)->toDateString();
        $to   = Carbon::now()->addDays(180)->toDateString();
        return CleaningSession::where('housekeeper_id', $userId)
            ->whereBetween('scheduled_date', [$from, $to])
            ->distinct()
            ->pluck('property_id');
    }

    public function __invoke(Request $request)
    {
        $u = Auth::user();
        $acting = $this->actingRole($request);
        abort_if($acting === 'forbidden', 403);

        // ----- KPIs -----
        $propIds = $this->visiblePropertyIds($acting, $u->id);

        // Properties count
        $propertiesCount = is_null($propIds)
            ? Property::count()
            : Property::whereIn('id', $propIds)->count();

        // Rooms count - count distinct rooms attached to visible properties
        $roomsCount = is_null($propIds)
            ? Room::count()
            : Room::whereHas('properties', fn($q) => $q->whereIn('properties.id', $propIds))->count();

        // Upcoming 7d (or overdue/active)
        $today = Carbon::today()->toDateString();
        $in7   = Carbon::today()->addDays(7)->toDateString();

        $upcoming7Count = $this->baseSessions($acting, $u->id)
            ->where(function($q) use ($today, $in7) {
                $q->whereBetween('scheduled_date', [$today, $in7])
                  ->orWhere(function($sub) use ($today) {
                      $sub->where('scheduled_date', '<', $today)
                          ->whereIn('status', ['pending', 'in_progress']);
                  });
            })
            ->count();

        // Completed last 30d
        $last30 = Carbon::today()->subDays(30)->toDateString();
        $completed30Count = $this->baseSessions($acting, $u->id)
            ->where('status', 'completed')
            ->whereBetween('scheduled_date', [$last30, $today])
            ->count();

        $stats = [
            'properties'     => $propertiesCount,
            'rooms'          => $roomsCount,
            'upcoming_7d'    => $upcoming7Count,
            'completed_30d'  => $completed30Count,
        ];

        // ----- Lists -----
        // Properties mini list
        $propertiesMini = Property::query()
            ->when(!is_null($propIds), fn($q) => $q->whereIn('id', $propIds))
            ->withCount('rooms')
            ->orderBy('name')
            ->limit(6)
            ->get(['id', 'name']);

        // Active & Upcoming sessions (next 30 days + any past uncompleted)
        $in30 = Carbon::today()->addDays(30)->toDateString();
        $upcomingSessions = $this->baseSessions($acting, $u->id)
            ->where(function($q) use ($today, $in30) {
                $q->whereBetween('scheduled_date', [$today, $in30])
                  ->orWhere(function($sub) use ($today) {
                      $sub->where('scheduled_date', '<', $today)
                          ->whereIn('status', ['pending', 'in_progress']);
                  });
            })
            ->orderBy('scheduled_date')
            ->limit(10)
            ->get(['id', 'property_id', 'housekeeper_id', 'scheduled_date', 'status']);

        // Recent completed (last 10)
        $recentSessions = $this->baseSessions($acting, $u->id)
            ->where('status', 'completed')
            ->orderByDesc('scheduled_date')
            ->limit(10)
            ->get(['id', 'property_id', 'housekeeper_id', 'scheduled_date', 'status']);

        // Housekeeper: assignments for the next 7 days (including overdue/in-progress)
        $hkTodaySessions = collect();
        if ($u->hasRole('housekeeper')) {
            $hkTodaySessions = CleaningSession::with(['property:id,name'])
                ->where('housekeeper_id', $u->id)
                ->where(function($q) use ($today) {
                    $end = \Illuminate\Support\Carbon::today()->addDays(30)->toDateString();
                    $q->whereBetween('scheduled_date', [$today, $end])
                      ->orWhere(function($sub) use ($today) {
                          $sub->whereDate('scheduled_date', '<', $today)
                              ->whereIn('status', ['pending', 'in_progress']);
                      });
                })
                ->orderBy('scheduled_date')
                ->limit(100)
                ->get(['id', 'property_id', 'scheduled_date', 'status', 'housekeeper_id']);
        }

        // Unscheduled checkouts from iCal
        $unscheduledCheckouts = $this->getUnscheduledCheckouts($acting, $u->id);

        return view('dashboard', [
            'stats'            => $stats,
            'propertiesMini'   => $propertiesMini,
            'upcomingSessions' => $upcomingSessions,
            'recentSessions'   => $recentSessions,
            'hkTodaySessions'  => $hkTodaySessions,
            'unscheduledCheckouts' => $unscheduledCheckouts,
            // not used by the blade but handy for debugging/scope badges if needed
            'acting'           => $acting,
        ]);
    }
    /**
     * Get list of unscheduled checkouts from iCal feeds.
     */
    private function getUnscheduledCheckouts(string $acting, int $userId)
    {
        // Only owners/admins/companies manage scheduling based on bookings
        if ($acting === 'housekeeper') {
            return collect();
        }

        $propertyIds = $this->visiblePropertyIds($acting, $userId);

        $properties = Property::query()
            ->when(!is_null($propertyIds), fn($q) => $q->whereIn('id', $propertyIds))
            ->where(function($q) {
                $q->where(function ($q) {
                    $q->whereNotNull('ical_url')
                        ->where('ical_url', '!=', '');
                })->orWhere(function ($q) {
                    $q->whereNotNull('airbnb_ical_url')
                        ->where('airbnb_ical_url', '!=', '');
                })->orWhere(function ($q) {
                    $q->whereNotNull('vrbo_ical_url')
                        ->where('vrbo_ical_url', '!=', '');
                });
            })
            ->get(['id', 'name', 'ical_url', 'airbnb_ical_url', 'vrbo_ical_url']);

        $today = Carbon::today();
        $limitDate = Carbon::today()->addMonths(1);

        foreach ($properties as $property) {
            $urls = [
                ['url' => $property->ical_url, 'source' => 'General'],
                ['url' => $property->airbnb_ical_url, 'source' => 'Airbnb'],
                ['url' => $property->vrbo_ical_url, 'source' => 'Vrbo'],
            ];

            foreach ($urls as $item) {
                if (empty($item['url'])) continue;

                $events = $this->icalService->fetchEvents($item['url'], $property->id . '_' . $item['source'], $item['source']);

                foreach ($events as $event) {
                    PropertyCheckout::updateOrCreate(
                        ['property_id' => $property->id, 'uid' => $event['uid']],
                        [
                            'checkout_date' => $event['end_date'],
                            'guest_name'    => $event['summary'] ?? 'Guest',
                            'source'        => $event['source'] ?? $item['source']
                        ]
                    );
                }
            }
        }

        // Now fetch from DB and filter by existing sessions
        $checkouts = PropertyCheckout::with('property')
            ->whereBetween('checkout_date', [$today->toDateString(), $limitDate->toDateString()])
            ->when(!is_null($propertyIds), fn($q) => $q->whereIn('property_id', $propertyIds))
            ->get();
        $unscheduled = collect();
        $previousLogin = session('previous_login_at');

        foreach ($checkouts as $checkout) {
            // Check if a session exists for this date and property
            $exists = CleaningSession::query()
                ->where('property_id', $checkout->property_id)
                ->whereDate('scheduled_date', $checkout->checkout_date)
                ->exists();

            if (!$exists) {
                $unscheduled->push([
                    'property_id'   => $checkout->property_id,
                    'property_name' => $checkout->property->name,
                    'checkout_date' => $checkout->checkout_date,
                    'guest_name'    => $checkout->guest_name,
                    'uid'           => $checkout->uid,
                    'source'        => $checkout->source,
                    'is_new'        => $previousLogin ? $checkout->first_seen_at > $previousLogin : false
                ]);
            }
        }

        // Sort by date soonest first
        return $unscheduled->sortBy('checkout_date')->values();
    }
}
