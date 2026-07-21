<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Fleet;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FleetController extends Controller
{
    /**
     * Get both districts and fleets in a single request.
     * Cached in browser for 1 hour with ETag for validation.
     */
    public function districtsAndFleets(): JsonResponse
    {
        $districts = DB::table('districts')
            ->orderBy('name')
            ->get(['id', 'name']);

        $fleets = DB::table('fleets')
            ->join('districts', 'fleets.district_id', '=', 'districts.id')
            ->select(
                'fleets.id',
                'fleets.fleet_number',
                'fleets.fleet_name',
                'fleets.district_id',
                'districts.name as district_name'
            )
            ->orderBy('fleets.fleet_number')
            ->get();

        $response = [
            'districts' => $districts,
            'fleets' => $fleets,
            // Sentinel row ids so the frontend can special-case
            // "Unaffiliated/None" (always-selectable fleet, blank-district
            // auto-fill) without string matching
            'none_district_id' => District::noneId(),
            'none_fleet_id' => Fleet::noneId(),
        ];

        return response()->json($response);
    }
}
