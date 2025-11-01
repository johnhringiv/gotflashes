# G.O.T. Flashes Challenge Tracker
## Product Requirements Document

### Executive Summary
A standalone web-based application to replace the current web form for tracking participant days sailing Lightnings as part of the G.O.T. Flashes Challenge. The system tracks annual sailing activity and automatically identifies award eligibility (10, 25, or 50+ days), with the goal of encouraging Lightning sailors to get on the water more frequently and creating friendly competition within fleets and districts.

---

## 1. User Management

### 1.1 User Registration
New users create accounts with the following information:

**Required Fields:**
- Email address (unique username/identifier)
- Password
- First name and last name
- Date of birth (for demographics tracking, especially sailors under 32)
- Gender (Male, Female, Non-binary, Prefer not to say)
- Physical mailing address (for award fulfillment)

**Optional Fields:**
- District (Lightning Class geographic region)
- Fleet (dynamically filtered by selected district)
- Yacht Club

**Year-Specific Memberships:**
District and fleet affiliations are tracked per calendar year. When users register, their current year membership is created. Benefits:
- Users can change districts/fleets over time while preserving historical accuracy
- Leaderboards reflect year-end affiliations for each specific year
- Automatic carry-forward from previous years when not updated
- Unaffiliated sailors supported (no district or fleet required)

*For detailed membership logic, see [membership-year-end-logic.md](membership-year-end-logic.md).*

**Note**: Users don't need to own a Lightning - crews and anyone who sails on Lightnings can participate.

### 1.2 User Authentication
- Secure login system using email and password
- Session persistence to keep users logged in across visits
- Logout functionality

### 1.3 Profile Management
Users can view and edit their profile information through a dedicated profile page:

**Editable Information:**
- Personal details: First name, last name, date of birth, gender
- Mailing address: Address line 1, address line 2 (optional), city, state, ZIP code, country
- Lightning Class affiliations: District, fleet, yacht club (optional)

**Profile Management Features:**
- Dedicated "Edit Profile" page accessible from user navigation
- Form automatically displays current information for easy updates
- Updates both personal information and Lightning Class affiliations for the current year
- Real-time validation ensures data quality (e.g., prevents invalid dates, validates district/fleet selections)
- Success confirmation message after saving changes
- Email address remains fixed after registration (serves as permanent account identifier)
- Secure access - users can only view and edit their own profile

### 1.4 Data Export
Users can export their complete profile and activity history:
- CSV format download with user profile data and all flash entries
- Includes year-appropriate district/fleet affiliations for each activity

### 1.5 User Roles
- **Regular Users**: Can track their own activities and view their progress
- **Award Administrators**: Responsible for viewing award eligibility and mailing physical awards to participants

---

## 2. Awards & Recognition System

### 2.1 Program Rules - What Counts
The following activities count toward G.O.T. Flashes awards:

**Sailing Days:**
- Any time spent sailing on ANY Lightning (not just your own boat)
- Counts whether you're the skipper or crew
- Goal: Get the boat off the dock!
- **Unlimited**: Sailing days always count toward awards

**Non-Sailing Days:**
The following activities may count toward your annual total:
- Lightning boat maintenance
- Lightning trailer maintenance
- On-the-water Race Committee work on days where Lightnings are racing
- **Limitation**: Maximum 5 non-sailing days per calendar year count toward awards
- Users can log unlimited non-sailing days, but only the first 5 count toward award totals
- Counter resets January 1st each year

**Award Calculation:**
- **Qualifying Days** = Sailing Days + Logged Non-Sailing Days (up to 5 per year)
- All logged activities count toward awards (system prevents logging ineligible activities)
- Awards are based on calendar year (January 1 - December 31)

**Date Restrictions:**
- No future dates allowed (tolerance: +1 day for timezone handling)
- One activity per date per user (duplicate prevention enforced)
- Activities must be logged by January 31st of the following year (grace period deadline)

**Grace Period & Editability:**
- **During January**: Can log/edit/delete both current year and previous year activities
- **Starting February 1st**: Previous year becomes read-only (view only)
- **Current year**: Always editable/deletable until grace period ends (following January 31st)
- Grace period allows one month to finalize previous year's activities before they're locked
- **Launch Year Exception**: The grace period only applies after the application's start year (2026, configurable via `START_YEAR` environment variable). In January 2026, users can only enter 2026 dates. Starting in January 2027 and beyond, the grace period allows entering previous year dates during January. This prevents users from logging activities before the application existed.

### 2.2 Award Tiers
Participants earn recognition at the following annual milestones:
- **10 days**: First tier award (badge image: `got-10-badge.png`)
- **25 days**: Second tier award (badge image: `got-25-badge.png`)
- **50+ days**: Third tier award (badge image: `got-50-badge.png`, including Burgee eligibility with burgee image: `burgee-50.jpg`)

**Award Badge Display:**
- Award badges are displayed as images, not text labels
- Award badges are displayed cumulatively - earning a higher tier shows all lower tier badges
- Example: A user with 50 days sees all three badge images (10-day, 25-day, and 50-day)
- Badges only appear once the threshold is met (no empty/placeholder badges shown below thresholds)
- At exactly 10, 25, or 50 days, the respective badge image appears immediately
- The 50-day award includes special "(Burgee)" designation in the display text

---

## 3. Logbook

### 3.1 Activity Entry
Users log activities using a simple form:

**Date Selection:**
- Multi-date selection: Users can select multiple dates at once using an interactive calendar
- Single-date edit mode available for editing existing entries
- All selected dates share the same activity details (type, location, notes)
- Duplicate prevention: existing entries cannot be re-selected
- Date restrictions: No future dates, respects grace period boundaries
- All-or-nothing validation: if any selected date has an error, no entries are created

**Activity Type (Required):**
- **Sailing on a Lightning** (always available, unlimited - see Section 2.1)
- **Boat/Trailer Maintenance** (non-sailing day - see Section 2.1 for counting rules)
- **Race Committee Work** (non-sailing day - see Section 2.1 for counting rules)

**Sailing Type (Required for sailing activities only):**
- Regatta, Club Race, Practice, or Day Sailing
- Purpose: Helps Lightning Class understand activity patterns for planning
- Cannot be specified for non-sailing activities

**Optional Fields:**
- Location (city, lake, venue)
- Sail Number (numeric)
- Notes (free-form text)

**Form Features & Validation:**
- System displays remaining non-sailing days counter (e.g., "3 of 5 remaining")
- Warning shown when logging 6th+ non-sailing day that won't count toward awards
- Date picker enforces restrictions from Section 2.1 (no future dates, no duplicates, respects grace period)
- Edit/delete buttons only appear for activities within editable date range (see Section 2.1 for grace period rules)

### 3.2 Activity History
- Users can view all their previously entered activities, including previous years
- Activities ordered by activity date (when it occurred), not by when it was logged
- Recently logged entries (created today) display a "Just logged" badge for visibility
- Edit/delete permissions follow grace period rules from Section 2.1
- Activity history displays:
    - Date
    - Activity type (sailing, maintenance, or race committee)
    - Optional details when provided (location, sail number, notes)
- Ability to filter or search activities by optional fields (future enhancement)

### 3.3 Progress Tracking
Users should see their current annual progress:
- Total sailing days (current year)
- Total non-sailing days logged
- Non-sailing days remaining (X of 5 available)
- Total qualifying days (toward awards)
- Visual progress indicators toward each award tier (10, 25, 50)
- Awards earned in current year
- Which award tier they've achieved
- Clear messaging about non-sailing day status:
    - If non-sailing days remaining: "X of 5 non-sailing days remaining"
    - If all 5 used: "All 5 non-sailing days used this year"

**Progress Display Thresholds:**
- Below 10 days: No award badges shown (not even empty placeholders)
- At 10 days: Bronze badge (10-day award) appears
- At 25 days: Bronze and Silver badges appear
- At 50+ days: All three badges appear (Bronze, Silver, Gold)
- "Next Award" indicator shows:
  - Below 10 days: "10 days" with days remaining countdown (e.g., "3 days to go")
  - At 10-24 days: "25 days" with days remaining countdown
  - At 25-49 days: "50 days" with days remaining countdown
  - At 50+ days: "Achievement" with burgee image and "All tiers completed!" message

### 3.4 Logbook Page Layout
The logbook page is the main landing page after login, displaying:
- Current year progress metrics (Section 3.3)
- Activity entry form (Section 3.1)
- Activity history with edit/delete capabilities (Section 3.2)

---

## 4. Leaderboards
Public leaderboards to encourage friendly competition and community engagement.

**Three Leaderboard Types:**
1. **Sailor (Individual)**: Rank sailors by total qualifying days
   - Display: Rank, Name, Days Logged, Fleet, District, Yacht Club
   - Only includes users with at least one activity in current year
2. **Fleet**: Rank fleets by total days logged by fleet members
   - Display: Rank, Fleet Number, Fleet Name, Total Days, Member Count
   - Only includes fleets with at least one member who has activities in current year
   - Unaffiliated users (no fleet) are excluded
3. **District**: Rank districts by total days logged by district members
   - Display: Rank, District Name, Total Days, Member Count
   - Only includes districts with at least one member who has activities in current year
   - Unaffiliated users (no district) are excluded

**Tie-Breaking Logic (applies to all leaderboards):**
1. Total qualifying flashes (primary sort)
2. Total sailing days (tie-breaker #1 - more sailing days wins)
3. First entry timestamp (tie-breaker #2 - earliest entry wins)
4. Alphabetical by name (tie-breaker #3 - for individual leaderboard)

**Display Features:**
- Non-sailing day cap (5 per year) enforced in all calculations
- Authenticated users see their own row highlighted
- Three tabs with instant switching (Sailor, Fleet, District)
- Pagination: 15 results per page
- Missing optional data displays as "—"

---

## 5. Award Fulfillment

The award fulfillment system provides administrators with tools to manage the physical award mailing process:

**Award Status System:**
- **Earned**: Awards computed in real-time based on user activity (not stored in database)
- **Processing**: Awards marked for preparation and printing (stored in database)
- **Sent**: Awards that have been mailed to participants (stored in database)

**Key Features:**
- View all awards grouped by status with year filtering
- Batch operations: Select multiple awards and mark as Processing, Sent, or reset to Earned
- Filter by status (Pending/Earned/Processing/Sent), award tier (10/25/50 days), and year
- Search by participant name or email
- Export selected awards to CSV for mailing label generation
- Discrepancy warnings when participants drop below award thresholds after processing

**Data Displayed:**
- Participant name, email, and mailing address
- Fleet and district affiliations (year-appropriate memberships)
- Award tier earned
- Total qualifying days for the year
- Date threshold was first reached
- Current award status
- Warning indicators for data discrepancies

**CSV Export:**
Export selected awards with complete mailing information including name, address, fleet, district, award tier, total days, and status for mail merge or shipping label generation.

**Audit Logging:**
All admin status changes are logged to a dedicated admin log channel with user identification, action details, and affected records.

---

## 6. System Requirements

### 6.1 Platform
- Web-based application accessible on desktop and mobile devices
- Self-hosted deployment
- Responsive design for various screen sizes

### 6.2 Data Management
- User accounts and activity data persist across sessions
- Historical data retained indefinitely (read-only after grace period)
- One activity per date per user (duplicate prevention enforced)

### 6.3 Security
- Secure user authentication and password protection
- Role-based access control (regular users and award administrators)
- Industry-standard security practices

*For technical architecture and implementation details, see [README.md](../README.md) and [CONTRIBUTING.md](CONTRIBUTING.md).*

---

## 7. Future Considerations

### Potential Enhancements (Out of Scope for Initial Release)
- Password reset/forgot password functionality
- Email notifications to award administrators when users reach award thresholds
- Email notifications to users when they earn awards
- **Historical year views**: Summary of each previous year's achievements, total days and awards earned per year, complete activity logs from prior years (read-only), with only current year activities counting toward current year awards
- Export activity reports to PDF or CSV
- Award certificates (downloadable PDFs)
- Social sharing features
---

## 8. Success Metrics

### Key Performance Indicators
- Number of registered participants year-over-year
- Average days logged per participant
- Percentage of users reaching each award tier (10, 25, 50 days)
- User retention rate (returning users year-over-year)
- Activity logging frequency (how often users log activities)
- Geographic distribution of participants (fleet/district level)
- Total Lightning days logged across all participants
- Completion rate (users who log at least one day)
- Age demographics of participants (with focus on sailors under 32 for membership growth initiatives)
- Participation rates and activity levels by age group

### Program Goals
- Increase Lightning sailing activity across the class
- Create friendly competition within fleets and districts
- Encourage crews and non-owners to participate
- Simplify tracking compared to old web form system
- Build year-over-year participation growth
- Attract and retain younger sailors (particularly those under 32) to grow class membership

---

## 9. Open Questions for Stakeholders

1. **Name of Awards**: What are the specific names/types of awards for each tier (10, 25, 50 days)?
2. **Branding**: U32 Image + transparent images without the barcode
---

## Document Control

**Last Updated**: October 29, 2025
