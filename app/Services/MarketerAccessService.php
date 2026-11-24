<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\BackyardFarmApplication;
use App\Models\RegionalTour;
use App\Models\RegionalTourRegistration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * The single answer to "what is this marketer allowed to see?"
 *
 * The two onboarding queues are scoped by different things: backyard
 * applications carry zone_id (a string) and no region, while tours and tour
 * registrations carry a region name. There is no point guessing a region from
 * a zone here — a marketer gets their own attributions plus their assigned
 * territory, and nothing else.
 *
 * Both the list screens AND the state-changing actions use these builders.
 * Scoping only the list would leave every record reachable by guessing an id,
 * which is the same as no scoping at all.
 */
class MarketerAccessService
{
    /** Backyard applications in the marketer's zone or attributed to them. */
    public function backyardQuery(Admin $marketer): ?Builder
    {
        if (! Schema::hasTable('backyard_farm_applications')
            || ! Schema::hasColumn('backyard_farm_applications', 'marketer_id')) {
            return null;
        }

        return BackyardFarmApplication::query()
            ->where(function (Builder $query) use ($marketer) {
                $query->where('marketer_id', $marketer->id);

                if ($marketer->zone_id) {
                    // The column is a string in this table; cast explicitly so
                    // the index is used and '1' never accidentally matches '01'.
                    $query->orWhere('zone_id', (string) $marketer->zone_id);
                }
            });
    }

    /** Tours in the marketer's region or led by them. */
    public function tourQuery(Admin $marketer): ?Builder
    {
        if (! Schema::hasTable('regional_tours')) {
            return null;
        }

        $regions = $this->regionsFor($marketer);

        return RegionalTour::query()
            ->where(function (Builder $query) use ($marketer, $regions) {
                $query->where('lead_marketer_id', $marketer->id);

                if ($regions !== []) {
                    $query->orWhereIn('region', $regions);
                }
            });
    }

    /** Tour registrations in the marketer's region or attributed to them. */
    public function registrationQuery(Admin $marketer): ?Builder
    {
        if (! Schema::hasTable('regional_tour_registrations')) {
            return null;
        }

        $regions = $this->regionsFor($marketer);

        return RegionalTourRegistration::query()
            ->where(function (Builder $query) use ($marketer, $regions) {
                $query->where('marketer_id', $marketer->id);

                if ($regions !== []) {
                    $query->orWhereIn('region', $regions);
                }
            });
    }

    /**
     * Region spellings the tours tables actually use.
     *
     * GHANA_REGIONS stores bare names ("Greater Accra"), while other parts of
     * the app use the suffixed form ("Greater Accra Region"). Matching both
     * keeps a marketer from being shown an empty queue because of a suffix.
     * No region on the account means no territory — fail closed, not open.
     *
     * @return string[]
     */
    public function regionsFor(Admin $marketer): array
    {
        $raw = trim((string) ($marketer->marketer_region ?? ''));
        if ($raw === '') {
            return [];
        }

        $bare = preg_replace('/\s+Region$/i', '', $raw) ?: $raw;

        $variants = array_values(array_unique(array_filter([
            $raw,
            $bare,
            $bare . ' Region',
        ])));

        // The same territory must produce the same list regardless of which
        // spelling the account happens to store; otherwise an orWhereIn scope
        // differs by input form and cached/compared results drift.
        sort($variants, SORT_STRING);

        return $variants;
    }
}
