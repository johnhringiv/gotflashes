<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ExportController extends Controller
{
    /**
     * Export user's data as CSV.
     * Includes all flashes with corresponding district and fleet information for each year.
     * Handles membership changes over the years by joining with the members table.
     */
    public function exportUserData(Request $request)
    {
        $user = $request->user();

        // Build query with joins to get all related data
        // Join flashes with members table based on the year of the flash
        // This ensures we get the correct district/fleet for each flash's year
        $data = $user->flashes()
            ->leftJoin('members', function ($join) {
                $join->on('members.user_id', '=', 'flashes.user_id')
                    ->whereRaw('members.year = strftime(\'%Y\', flashes.date)');
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
            ->get();

        // Generate CSV
        $csv = $this->generateCsv($user, $data);

        // Return as downloadable file
        $filename = 'got-flashes-export-'.now()->format('Y-m-d').'.csv';

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Generate CSV content from user data.
     */
    private function generateCsv($user, $data): string
    {
        $csv = '';

        // Header section with user info
        $csv .= "G.O.T. Flashes Data Export\n";
        $csv .= 'Export Date: '.now()->format('Y-m-d')."\n";
        $csv .= "\n";
        $csv .= "User Information\n";
        $csv .= "Name:,{$user->name}\n";
        $csv .= "Email:,{$user->email}\n";
        $csv .= 'Date of Birth:,'.($user->date_of_birth ? $user->date_of_birth->format('Y-m-d') : '')."\n";
        $csv .= "Gender:,{$user->gender}\n";
        $csv .= "Address:,\"{$user->address_line1}".($user->address_line2 ? ', '.$user->address_line2 : '')."\"\n";
        $csv .= "City:,{$user->city}\n";
        $csv .= "State:,{$user->state}\n";
        $csv .= "Zip:,{$user->zip_code}\n";
        $csv .= "Country:,{$user->country}\n";
        $csv .= 'Yacht Club:,'.($user->yacht_club ?: '')."\n";
        $csv .= "\n";

        // Flashes data section
        $csv .= "Activity Log\n";
        $csv .= '"Date","Activity Type","Event Type","Location","Sail Number","District","Fleet Number","Fleet Name","Notes","Created At","Updated At"'."\n";

        foreach ($data as $flash) {
            // Format date as Y-m-d without time
            $dateValue = $flash->date instanceof \Carbon\Carbon
                ? $flash->date->format('Y-m-d')
                : $flash->date;

            $csv .= $this->escapeCsvValue($dateValue);
            $csv .= ','.$this->escapeCsvValue($flash->activity_type);
            $csv .= ','.$this->escapeCsvValue($flash->event_type);
            $csv .= ','.$this->escapeCsvValue($flash->location);
            $csv .= ','.$this->escapeCsvValue($flash->sail_number);
            $csv .= ','.$this->escapeCsvValue($flash->district_name);
            $csv .= ','.$this->escapeCsvValue($flash->fleet_number);
            $csv .= ','.$this->escapeCsvValue($flash->fleet_name);
            $csv .= ','.$this->escapeCsvValue($flash->notes);
            $csv .= ','.$this->escapeCsvValue($flash->created_at);
            $csv .= ','.$this->escapeCsvValue($flash->updated_at);
            $csv .= "\n";
        }

        return $csv;
    }

    /**
     * Escape CSV value (handle quotes, commas, newlines).
     */
    private function escapeCsvValue($value): string
    {
        if ($value === null) {
            return '""';
        }

        $value = (string) $value;

        // If value contains comma, quote, or newline, wrap in quotes and escape quotes
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            $value = '"'.str_replace('"', '""', $value).'"';
        } else {
            $value = '"'.$value.'"';
        }

        return $value;
    }
}
