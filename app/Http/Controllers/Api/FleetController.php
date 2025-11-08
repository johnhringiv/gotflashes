<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        ];

        return response()->json($response);
    }
}
