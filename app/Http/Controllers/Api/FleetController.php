<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function fleetsByDistrict(Request $request, int $districtId): JsonResponse
    {
        // Validate that the district ID exists
        $validated = $request->merge(['districtId' => $districtId])->validate([
            'districtId' => 'required|integer|exists:districts,id',
        ]);

        $fleets = DB::table('fleets')
            ->where('district_id', $validated['districtId'])
            ->orderBy('fleet_number')
            ->get(['id', 'fleet_number', 'fleet_name']);

        return response()->json($fleets);
    }
}
