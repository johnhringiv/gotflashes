<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FleetController extends Controller
{
    /**
     * Get all districts
     */
    public function districts(): JsonResponse
    {
        $districts = DB::table('districts')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($districts);
    }

    /**
     * Get all fleets with their district information
     */
    public function fleets(): JsonResponse
    {
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

        return response()->json($fleets);
    }

    /**
     * Get fleets by district
     */
    public function fleetsByDistrict(int $districtId): JsonResponse
    {
        $fleets = DB::table('fleets')
            ->where('district_id', $districtId)
            ->orderBy('fleet_number')
            ->get(['id', 'fleet_number', 'fleet_name']);

        return response()->json($fleets);
    }
}
