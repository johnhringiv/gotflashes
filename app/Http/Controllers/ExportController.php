<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ExportController extends Controller
{
    /**
     * Export user's data as CSV.
     * Includes all flashes with corresponding district and fleet information for each year.
     * Handles membership changes over the years by joining with the members table.
     * Uses streaming for memory efficiency with large datasets.
     */
    public function exportUserData(Request $request)
    {
        $user = $request->user();

        $filename = 'got-flashes-export-'.now()->format('Y-m-d').'.csv';

        $callback = function () use ($user) {
            $handle = fopen('php://output', 'w');

            // Write UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Write header row with all columns
            fputcsv($handle, [
                'Name',
                'Email',
                'Date of Birth (YYYY-MM-DD)',
                'Gender',
                'Address',
                'City',
                'State',
                'Zip',
                'Country',
                'Yacht Club',
                'Date (YYYY-MM-DD)',
                'Activity Type',
                'Event Type',
                'Location',
                'Sail Number',
                'District',
                'Fleet Number',
                'Fleet Name',
                'Notes',
                'Created At',
                'Updated At',
            ]);

            // Pre-build user data array (avoid repeated property access in loop)
            $userData = [
                $user->name,
                $user->email,
                $user->date_of_birth ? (string) $user->date_of_birth : '',
                $user->gender ?? '',
                $user->address_line1.($user->address_line2 ? ', '.$user->address_line2 : ''),
                $user->city ?? '',
                $user->state ?? '',
                $user->zip_code ?? '',
                $user->country ?? '',
                $user->yacht_club ?? '',
            ];

            // Stream flashes data in chunks
            $user->flashes()
                ->leftJoin('members', function ($join) {
                    $join->on('members.user_id', '=', 'flashes.user_id')
                        ->whereRaw("members.year = CAST(strftime('%Y', flashes.date) AS INTEGER)");
                })
                ->leftJoin('districts', 'members.district_id', '=', 'districts.id')
                ->leftJoin('fleets', 'members.fleet_id', '=', 'fleets.id')
                ->select([
                    'flashes.date',
                    'flashes.activity_type',
                    'flashes.event_type',
                    'flashes.location',
                    'flashes.sail_number',
                    'flashes.notes',
                    'flashes.created_at',
                    'flashes.updated_at',
                    'districts.name as district_name',
                    'fleets.fleet_number',
                    'fleets.fleet_name',
                ])
                ->orderBy('flashes.date', 'desc')
                ->chunk(100, function ($flashes) use ($handle, $userData) {
                    foreach ($flashes as $flash) {
                        // Format date as Y-m-d without time
                        // @phpstan-ignore-next-line
                        $dateValue = $flash->date instanceof \Carbon\Carbon
                            ? $flash->date->format('Y-m-d')
                            : $flash->date;

                        // Merge pre-built user data with flash data
                        fputcsv($handle, array_merge($userData, [
                            $dateValue,
                            $flash->activity_type ?? '',
                            $flash->event_type ?? '',
                            $flash->location ?? '',
                            $flash->sail_number ?? '',
                            $flash->district_name ?? '',
                            $flash->fleet_number ?? '',
                            $flash->fleet_name ?? '',
                            $flash->notes ?? '',
                            $flash->created_at ?? '',
                            $flash->updated_at ?? '',
                        ]));
                    }
                });

            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'X-Download-Options' => 'noopen',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
