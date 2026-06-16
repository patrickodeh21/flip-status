<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\CarbonPeriod;
use Carbon\Carbon;
use App\Models\CleaningSession;


use App\Models\User;
use App\Models\Property;
use App\Models\PropertyCheckout;
use App\Models\Room;
use App\Services\ICalService;

class CalendarController extends Controller
{
    private $icalService;

    public function __construct(ICalService $icalService) {
        $this->icalService = $icalService;
    }

    /**
     * Determine acting role per request.
     * If user has admin, admin wins unless ?as=owner given and user also has owner.
     */
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
        if ($isHK)    return 'housekeeper';

        // Final fallback: if no role is found, treat as owner to avoid 403 during demo/setup
        return 'owner';
    }

    public function index(Request $request)
    {
        $u = Auth::user();
        $acting = $this->actingRole($request);
        abort_if($acting === 'forbidden', 403);

        // Determine user's timezone from their properties or default to US Eastern
        $userTz = 'America/New_York'; // Default for US users
        if ($u) {
            $propTz = Property::query()
                ->when($acting === 'housekeeper', function($q) use ($u) {
                    $q->whereIn('id', function($sub) use ($u) {
                        $sub->select('property_id')->from('cleaning_sessions')->where('housekeeper_id', $u->id);
                    });
                })
                ->when($acting === 'owner', fn($q) => $q->where('owner_id', $u->id))
                ->whereNotNull('timezone')
                ->where('timezone', '!=', '')
                ->value('timezone');

            if ($propTz) {
                $userTz = $propTz;
            }
        }

        $today = now($userTz)->toDateString();

        // Month selection (YYYY-MM), defaults to current month
        $monthParam = (string) $request->query('month', now($userTz)->format('Y-m'));
        $monthStart = Carbon::createFromFormat('Y-m-d', $monthParam . '-01')->startOfMonth();
        $monthEnd   = (clone $monthStart)->endOfMonth();

        // Grid boundaries (full weeks) - Start on Sunday
        $gridStart = (clone $monthStart)->startOfWeek(Carbon::SUNDAY);
        $gridEnd   = (clone $monthEnd)->endOfWeek(Carbon::SUNDAY);

        // Day selected (for sidebar/list)
        $selectedDay = $request->query('day');
        $selectedDay = $selectedDay ? Carbon::parse($selectedDay)->toDateString() : null;

        // Base query scoped by acting role
        $q = CleaningSession::query()
            ->with(['property:id,name,owner_id', 'housekeeper:id,name'])
            ->whereBetween('scheduled_date', [$gridStart->toDateString(), $gridEnd->toDateString()]);

        // Helpers to get visible property IDs
        $visiblePropIds = null;

        if ($acting === 'housekeeper') {
            $q->where('housekeeper_id', $u->id);
        } elseif ($acting === 'owner') {
             if ($u->hasRole('company')) {
                  // Company Logic
                  $companyPropIds = Property::where(function($qq) use ($u) {
                      $qq->where('owner_id', $u->id)
                        ->orWhereIn('owner_id', function($sub) use ($u) {
                            $sub->select('id')->from('users')->where('owner_id', $u->id);
                        })
                        ->orWhereIn('owner_id', function($sub) use ($u) {
                            $sub->select('owner_id')->from('housekeeper_owner')->where('housekeeper_id', $u->id);
                        })
                        ->orWhereHas('users', function($sub2) use ($u) {
                            $sub2->where('users.id', $u->id);
                        });
                  })->pluck('id');

                  $q->whereIn('property_id', $companyPropIds);
                  $visiblePropIds = $companyPropIds;
             } else {
                  $q->whereHas('property', fn($p) => $p->where('owner_id', $u->id));
                  $visiblePropIds = Property::where('owner_id', $u->id)->pluck('id');
             }
        } // admin -> no scope

        $sessions = $q->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->get();

        // Group sessions by date for calendar dots/counts
        $byDate = $sessions->groupBy(fn($s) => Carbon::parse($s->scheduled_date)->toDateString());

        // --- Fetch Unscheduled Checkouts (iCal) for the Grid Range ---
        $unscheduledByDate = collect();

        if ($acting !== 'housekeeper') {
             // Get properties with iCal
            $icalProps = Property::query()
                 ->where(function ($q) use ($acting, $visiblePropIds) {
                     if ($acting === 'owner' && $visiblePropIds) {
                         $q->whereIn('id', $visiblePropIds);
                     }
                 })
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

            foreach ($icalProps as $prop) {
                 $urls = [
                     ['url' => $prop->ical_url, 'source' => 'General'],
                     ['url' => $prop->airbnb_ical_url, 'source' => 'Airbnb'],
                     ['url' => $prop->vrbo_ical_url, 'source' => 'Vrbo'],
                 ];

                 foreach ($urls as $item) {
                     if (empty($item['url'])) continue;

                     $events = $this->icalService->fetchEvents($item['url'], $prop->id . '_' . $item['source'], $item['source']);

                     foreach ($events as $event) {
                         PropertyCheckout::updateOrCreate(
                             ['property_id' => $prop->id, 'uid' => $event['uid']],
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
            $checkoutsQuery = PropertyCheckout::whereBetween('checkout_date', [$gridStart->toDateString(), $gridEnd->toDateString()]);

            if (!is_null($visiblePropIds)) {
                $checkoutsQuery->whereIn('property_id', $visiblePropIds);
            }

            $checkouts = $checkoutsQuery->get();

            foreach ($checkouts as $checkout) {
                $exists = CleaningSession::where(function($q) use ($checkout) {
                    $q->where('checkout_id', $checkout->id)
                      ->orWhere(function($subQ) use ($checkout) {
                          $subQ->where('property_id', $checkout->property_id)
                               ->whereDate('scheduled_date', $checkout->checkout_date);
                      });
                })->exists();

                if (!$exists) {
                    $unscheduledByDate->push([
                        'id' => $checkout->id,
                        'date' => $checkout->checkout_date,
                        'property_id' => $checkout->property_id,
                        'property_name' => $checkout->property->name,
                        'guest_name' => $checkout->guest_name,
                        'source' => $checkout->source,
                        'type' => 'unscheduled'
                    ]);
                }
            }
        }

        $unscheduledByDate = $unscheduledByDate->groupBy('date');


        // Optional: a list for the selected day, sorted by time (earliest to latest)
        $daySessions = $selectedDay ? ($byDate[$selectedDay] ?? collect()) : collect();
        $dayUnscheduled = $selectedDay ? ($unscheduledByDate[$selectedDay] ?? collect()) : collect();

        // Sort day sessions by scheduled_time (earliest to latest)
        if ($daySessions->isNotEmpty()) {
            $daySessions = $daySessions->sortBy(function($session) {
                return $session->scheduled_time ? $session->scheduled_time->format('H:i:s') : '23:59:59';
            })->values();
        }

        // Build day cells
        $days = [];
        foreach (CarbonPeriod::create($gridStart, '1 day', $gridEnd) as $date) {
            $d = $date->toDateString();
            $days[] = [
                'date'          => $d,
                'isToday'       => $d === $today,
                'inMonth'       => $date->betweenIncluded($monthStart, $monthEnd),
                'sessionCount'  => ($byDate[$d] ?? collect())->count(),
                'unscheduledCount' => ($unscheduledByDate[$d] ?? collect())->count(),
            ];
        }

        // Prev/next month params
        $prevMonth = (clone $monthStart)->subMonth()->format('Y-m');
        $nextMonth = (clone $monthStart)->addMonth()->format('Y-m');

        return view('calendar.index', [
            'acting'      => $acting,
            'monthStart'  => $monthStart,
            'prevMonth'   => $prevMonth,
            'nextMonth'   => $nextMonth,
            'days'        => $days,
            'selectedDay' => $selectedDay,
            'daySessions' => $daySessions,
            'dayUnscheduled' => $dayUnscheduled,
        ]);
    }
}
