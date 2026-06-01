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
            // Use empty escape parameter to prevent CSV injection
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
            ], ',', '"', '');

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

            // Load memberships (with their district/fleet) once so the carry-forward
            // resolution below is in-memory and stays consistent with the rest of the
            // app — see User::membershipForYear(), which the Leaderboard mirrors.
            $user->loadMissing('members.district', 'members.fleet');

            // Stream flashes data in chunks
            $user->flashes()
                ->orderBy('date', 'desc')
                ->chunk(100, function ($flashes) use ($handle, $userData, $user) {
                    foreach ($flashes as $flash) {
                        // Format date as Y-m-d without time
                        // @phpstan-ignore-next-line
                        $dateValue = $flash->date instanceof \Carbon\Carbon
                            ? $flash->date->format('Y-m-d')
                            : $flash->date;

                        // Resolve affiliation via the canonical carry-forward method
                        // (uses the most recent membership on or before the flash year),
                        // not an exact-year match — so the CSV agrees with the leaderboard.
                        $member = $user->membershipForYear((int) substr((string) $dateValue, 0, 4));

                        // Merge pre-built user data with flash data
                        fputcsv($handle, array_merge($userData, [
                            $dateValue,
                            $flash->activity_type ?? '',
                            $flash->event_type ?? '',
                            $flash->location ?? '',
                            $flash->sail_number ?? '',
                            data_get($member, 'district.name', ''),
                            data_get($member, 'fleet.fleet_number', ''),
                            data_get($member, 'fleet.fleet_name', ''),
                            $flash->notes ?? '',
                            $flash->created_at ?? '',
                            $flash->updated_at ?? '',
                        ]), ',', '"', '');
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
