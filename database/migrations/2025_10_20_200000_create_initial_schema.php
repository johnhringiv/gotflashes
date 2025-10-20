<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update users table with registration fields
        Schema::table('users', function (Blueprint $table) {
            // Remove the default 'name' column from Laravel
            $table->dropColumn('name');

            // Add detailed user fields for registration
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth');
            $table->enum('gender', ['male', 'female', 'non_binary', 'prefer_not_to_say']);
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city');
            $table->string('state');
            $table->string('zip_code', 20);
            $table->string('country');
            $table->string('yacht_club')->nullable();
            $table->boolean('is_admin')->default(false);
        });

        // Create districts table
        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // Create fleets table
        Schema::create('fleets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained()->onDelete('cascade');
            $table->integer('fleet_number')->unique();
            $table->string('fleet_name');
            $table->timestamps();

            $table->index('district_id');
            $table->index('fleet_number');
        });

        // Create members table (year-specific user affiliations)
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('district_id')->nullable()->constrained('districts')->onDelete('set null');
            $table->foreignId('fleet_id')->nullable()->constrained('fleets')->onDelete('set null');
            $table->integer('year');
            $table->timestamps();

            // Unique constraint: one membership record per user per year
            $table->unique(['user_id', 'year']);

            // Indexes for efficient queries
            $table->index('year');
            $table->index('district_id');
            $table->index('fleet_id');
        });

        // Create flashes table (activity tracking)
        Schema::create('flashes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('activity_type', ['sailing', 'maintenance', 'race_committee']);
            $table->enum('event_type', ['regatta', 'club_race', 'practice', 'leisure'])->nullable();
            $table->string('location', 255)->nullable();
            $table->integer('sail_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Ensure one activity per date per user
            $table->unique(['user_id', 'date']);

            // Indexes for common queries
            $table->index('date');
            $table->index('created_at', 'idx_flashes_created_at');

            // Composite index for leaderboard queries
            $table->index(['user_id', 'date', 'activity_type'], 'idx_flashes_leaderboard');
        });

        // Expression index for year filtering (SQLite specific optimization)
        DB::statement("CREATE INDEX idx_flashes_year ON flashes(strftime('%Y', date))");

        // Seed reference data: Districts and Fleets
        // This data is required for the application to function
        $this->seedDistrictsAndFleets();
    }

    /**
     * Seed districts and fleets reference data
     */
    private function seedDistrictsAndFleets(): void
    {
        $fleetData = [
            ['Argentina', 522, 'Rosario'],
            ['Australia', 519, 'Sydney'],
            ['Brazil', 147, 'Sao Paulo'],
            ['Brazil', 462, 'Guarapiranga Lake'],
            ['Brazil', 524, 'Guara'],
            ['California', 194, 'Mission Bay Yacht Club'],
            ['California', 372, 'San Francisco Bay Area'],
            ['Central Atlantic', 173, 'Brant Beach Yacht Club'],
            ['Central Atlantic', 196, 'Surf City'],
            ['Central Atlantic', 228, 'Riverton Yacht Club'],
            ['Central Atlantic', 26, 'Little Egg Harbor'],
            ['Central Atlantic', 3, 'Bay Head Yacht Club'],
            ['Central Atlantic', 335, 'Barnegat Light Yacht Club'],
            ['Central Atlantic', 34, 'Metedeconk River Yacht Club'],
            ['Central Atlantic', 430, 'Ocean City Yacht Club'],
            ['Central Atlantic', 491, 'Lake Nockamixon'],
            ['Central Canada', 279, 'Temple Reef Sailing Club'],
            ['Central New York', 1, 'Skaneateles Country Club'],
            ['Central New York', 108, 'Great Sodus Bay'],
            ['Central New York', 164, 'Willow Bank Yacht Club'],
            ['Central New York', 225, 'Henderson Harbor'],
            ['Central New York', 252, 'Keuka Lake'],
            ['Central New York', 338, 'Galway Lake'],
            ['Central New York', 4, 'Lake Delta Yacht Club'],
            ['Central New York', 484, 'Ithaca'],
            ['Central New York', 52, 'Rochester Yacht Club'],
            ['Central New York', 61, 'Pultneyville Yacht Club'],
            ['Central New York', 77, 'Newport Yacht Club'],
            ['Central New York', 9, 'Crescent Yacht Club'],
            ['Chile', 318, 'Algarrobo Yacht Club'],
            ['Chile', 490, 'Aculeo Lake'],
            ['Chile', 514, 'Flota Sur'],
            ['Colombia', 501, 'Club Nautico El Portillo'],
            ['Colombia', 73, 'Club Nautico Del Muna'],
            ['Connecticut/Rhode Island', 126, 'Cedar Point Yacht Club'],
            ['Connecticut/Rhode Island', 129, 'Madison Beach Yacht Club'],
            ['Connecticut/Rhode Island', 134, 'Noroton'],
            ['Connecticut/Rhode Island', 6, 'Housatonic Boat Club'],
            ['Connecticut/Rhode Island', 85, 'Niantic Bay Yacht Club'],
            ['Dixie', 192, 'Havre de Grace'],
            ['Dixie', 253, 'Susquehanna Yacht Club'],
            ['Dixie', 314, 'Sassafras River'],
            ['Dixie', 325, 'Rehoboth Bay Sailing Association'],
            ['Dixie', 329, 'Severn Sailing Association'],
            ['Dixie', 50, 'Potomac River Sailing Association'],
            ['Dixie', 508, 'Solomons'],
            ['Dixie', 509, 'Hampton Roads - limited activity'],
            ['Dixie', 518, 'Poquoson River'],
            ['Ecuador', 405, 'Salinas Yacht Club'],
            ['Finland', 166, 'Helsinki'],
            ['Finland', 298, 'Kotka'],
            ['Finland', 328, 'Jyvaskyla'],
            ['Finland', 456, 'Tuusulanjarvi'],
            ['Florida', 109, 'St Petersburg'],
            ['Florida', 226, 'Biscayne Bay'],
            ['Florida', 502, 'The Suncoast Fleet'],
            ['Florida', 526, 'Central Florida'],
            ['Greece', 251, 'Salamis Fleet'],
            ['Greece', 286, 'Parthenon Fleet'],
            ['Greece', 525, 'Lightning Sailing Academy'],
            ['Indiana', 154, 'Wawasee Yacht Club'],
            ['Indiana', 270, 'Indianapolis Sailing Club'],
            ['Italy', 449, 'Marsala'],
            ['Lake Erie', 115, 'Cuba Lake Yacht Club'],
            ['Lake Erie', 12, 'Buffalo Canoe Club'],
            ['Lake Erie', 146, 'Toronto Bay'],
            ['Lake Erie', 180, 'Conneaut Lake Yacht Club'],
            ['Lake Erie', 19, 'Chautauqua Lake Yacht Club'],
            ['Lake Erie', 198, 'Chautauqua'],
            ['Lake Erie', 24, 'Erie Yacht Club'],
            ['Lake Erie', 36, 'Pymatuning Yacht Club'],
            ['Lake Erie', 47, 'Silver Lake Yacht Club'],
            ['Long Island', 178, 'Great South Bay'],
            ['Long Island', 431, 'Southampton Yacht Club'],
            ['Long Island', 506, 'Orient Yacht Club'],
            ['Metropolitan', 16, 'Paupack'],
            ['Metropolitan', 25, 'Lake Mohawk Yacht Club'],
            ['Metropolitan', 70, 'Red Bank of the Shrewsbury'],
            ['Metropolitan', 75, 'Nyack Boat Club'],
            ['Mexico', 523, 'Valle de Bravo'],
            ['Michigan', 110, 'Higgins Lake'],
            ['Michigan', 216, 'Saginaw Bay'],
            ['Michigan', 233, 'Ford Yacht Club'],
            ['Michigan', 31, 'Devils Lake Yacht Club'],
            ['Michigan', 374, 'Douglas Lake'],
            ['Michigan', 387, 'Lansing Sailing Club'],
            ['Michigan', 42, 'Western Lake Erie'],
            ['Michigan', 51, 'Crescent Sail Yacht Club'],
            ['Michigan', 512, 'Boyne City Yacht Club'],
            ['Michigan', 53, 'Lake Fenton Sailing Club'],
            ['Michigan', 54, 'Pontiac Yacht Club'],
            ['Midwest', 112, 'Green Bay Sailing Club'],
            ['Midwest', 187, 'Sheboygan'],
            ['Midwest', 442, 'Fond du Lac/Winnebago'],
            ['Midwest', 5, 'Chicago Corinthian Yacht Club'],
            ['Midwest', 79, 'South Shore Yacht Club'],
            ['Mississippi Valley', 262, 'Harbor Island Yacht Club'],
            ['Mississippi Valley', 266, 'Carlyle Sailing Association'],
            ['Mississippi Valley', 274, 'Delta Sailing Association'],
            ['Mississippi Valley', 74, 'Decatur'],
            ['New England', 121, 'Merrimack River'],
            ['New England', 189, 'Marblehead'],
            ['New England', 273, 'Massabesic Yacht Club'],
            ['New England', 301, 'Lake Champlain'],
            ['New England', 332, 'Squam Lake'],
            ['New England', 493, 'Bow Lake'],
            ['Nigeria', 510, 'Lagos Yacht Club'],
            ['Ohio', 150, 'Mansfield Sailing Club'],
            ['Ohio', 23, 'Indian Lake Yacht Club'],
            ['Ohio', 27, 'Leatherlips Yacht Club'],
            ['Ohio', 303, 'Cowan Lake Sailing Association'],
            ['Ohio', 386, 'Sandusky Bay'],
            ['Ohio', 71, 'Rocky River'],
            ['Pacific Northwest', 229, 'Chinook'],
            ['Pacific Northwest', 283, 'Columbia'],
            ['Pacific Northwest', 527, 'Royal Vancouver YC Scott Point'],
            ['Pacific Northwest', 90, 'Kitsilano Yacht Club'],
            ['Peru', 265, 'Yacht Club LaPunta'],
            ['Southeastern', 257, 'Clarks Hill'],
            ['Southeastern', 348, 'Lake Lanier'],
            ['Southeastern', 415, 'Lake Norman'],
            ['Southeastern', 429, 'Greater Charleston'],
            ['Southeastern', 511, 'Cape Fear'],
            ['Southern', 135, 'Mobile Bay'],
            ['Southern', 62, 'Southern Yacht Club'],
            ['St Lawrence Valley', 215, 'Royal St Lawrence Yacht Club'],
            ['St Lawrence Valley', 516, 'Beaconsfield Yacht Club'],
            ['Switzerland', 358, 'Flotte Murtensee'],
            ['Texas', 435, 'Rush Creek Yacht Club'],
            ['Texas', 521, 'Houston'],
            ['US@Large', 488, 'Rocky Mountain'],
        ];

        // Extract unique districts
        $districts = array_unique(array_column($fleetData, 0));
        sort($districts);

        // Insert districts and build ID mapping
        $districtIds = [];
        foreach ($districts as $districtName) {
            $id = DB::table('districts')->insertGetId([
                'name' => $districtName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $districtIds[$districtName] = $id;
        }

        // Insert fleets
        foreach ($fleetData as [$districtName, $fleetNumber, $fleetName]) {
            DB::table('fleets')->insert([
                'district_id' => $districtIds[$districtName],
                'fleet_number' => $fleetNumber,
                'fleet_name' => $fleetName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop in reverse order due to foreign keys
        DB::statement('DROP INDEX IF EXISTS idx_flashes_year');
        Schema::dropIfExists('flashes');
        Schema::dropIfExists('members');
        Schema::dropIfExists('fleets');
        Schema::dropIfExists('districts');

        // Restore users table to original state
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'date_of_birth',
                'gender',
                'address_line1',
                'address_line2',
                'city',
                'state',
                'zip_code',
                'country',
                'yacht_club',
                'is_admin',
            ]);

            $table->string('name');
        });
    }
};