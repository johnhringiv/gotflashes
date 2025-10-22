# G.O.T. Flashes Challenge Tracker
## Product Requirements Document

### Executive Summary
A standalone web-based application to replace the current web form for tracking participant days sailing Lightnings as part of the G.O.T. Flashes Challenge. The system tracks annual sailing activity and automatically identifies award eligibility (10, 25, or 50+ days), with the goal of encouraging Lightning sailors to get on the water more frequently and creating friendly competition within fleets and districts.

---

## 1. User Management

### 1.1 User Registration
New users can create accounts with the following information:
- Email address (serves as unique username/identifier)
- Password (secure authentication)
- First Name
- Last Name
- Date of Birth (for demographics and membership growth analytics, particularly tracking participation of sailors under 32)
- Gender (optional - options: Male, Female, Non-binary, Prefer not to say)
- Physical mailing address
- District (optional)
- Yacht Club (optional)
- Fleet Number (optional - dynamically filtered by selected district)

**Year-Specific Memberships:**
District and fleet affiliations are tracked per calendar year rather than as a single static value. When users register, a membership record is created for the current year with their selected district and fleet. This system allows:
- Users to change districts or fleets over time while maintaining historical accuracy
- Leaderboards to reflect year-end affiliations (the district/fleet users belonged to during each specific year)
- Automatic carry-forward of memberships from previous years when not explicitly updated
- Support for unaffiliated sailors (those without a district or fleet)

For detailed information on membership logic and year-end processing, see [membership-year-end-logic.md](membership-year-end-logic.md).

**Note**: Users do not need to be Lightning owners - crews and anyone who sails on Lightnings can participate.

### 1.2 User Authentication
- Secure login system using email and password
- Session persistence to keep users logged in across visits
- Logout functionality
- Password reset capability

### 1.3 Profile Management
- Users can view their profile information
- Users can update their address, district, yacht club, fleet number, gender, and date of birth
- Email address should remain fixed after registration (serves as permanent identifier)

### 1.4 User Roles
- **Regular Users**: Can track their own activities and view their progress
- **Award Administrators**: Responsible for viewing award eligibility and mailing physical awards to participants

---

## 2. Activity Tracking

### 2.1 Program Rules - What Counts
The following activities count toward G.O.T. Flashes awards:

**Sailing Days:**
- Any time spent sailing on ANY Lightning (not just your own boat)
- Counts whether you're the skipper or crew
- Even one hour on a Lightning counts as a full "day"
- Goal: Get the boat off the dock!

**Boat Work Days (Non-sailing days):**
The following activities may count toward your annual total:
- Lightning boat maintenance
- Lightning trailer maintenance
- On-the-water Race Committee work on days where Lightnings are racing
- **Limitation**: Maximum 5 "non-sailing day" days total per calendar year

### 2.2 Activity Entry
Users log activities using a simple form for each day:
- Date of activity (cannot duplicate an existing entry date)
- Activity type (always available options):
    - **Sailing on a Lightning**
    - **Boat/Trailer Maintenance (non-sailing day)** - Available until 5 non-sailing days used
    - **Race Committee Work (non-sailing day)** - Available until 5 non-sailing days used
- **Sailing Type** (required when activity type is "Sailing"):
    - **Regatta** - Competitive racing events
    - **Club Race** - Local club racing
    - **Practice** - Practice sailing sessions
    - **Day Sailing** - Recreational sailing
    - **Purpose**: Helps the Lightning Class understand constituent activities and sailing patterns for analytics and planning
- **Optional fields** (enhance tracking and create richer records):
    - Location (city, lake, venue)
    - Sail Number (must be numeric)
    - Notes (free-form text)

**Non-sailing day Entry Behavior:**
- All users have 5 non-sailing day slots per calendar year that count toward awards
- Users can log non-sailing day activities at any time (no restrictions)
- After 5 non-sailing days are logged, additional non-sailing days can still be logged but won't count toward awards
- Warning message displayed when logging 6th+ non-sailing day to inform user it won't count toward awards
- Users are encouraged to continue logging all Lightning-related activity for complete records

**Benefits of this approach:**
- Simple, straightforward rules - just a maximum of 5 non-sailing days per year count toward awards
- Users can log all their Lightning activity without restrictions
- Clear warning messaging when logging non-counting days
- Encourages complete activity tracking while maintaining award integrity

**Edit Restrictions:**
- Users can edit or delete activities from the current calendar year only
- Activities from previous years are view-only and cannot be modified
- Users cannot create multiple activities for the same date
- All activities must be logged by January 31st of the following year (grace period for logging previous year's activities)

**Date Entry Restrictions:**
- Users cannot log activities with future dates
- System allows dates up to +1 day from server time to accommodate timezone differences
- This ensures users in any timezone can log today's activities without restriction
- Browser date picker enforces maximum date before submission
- Server-side validation prevents circumventing browser restrictions

### 2.3 Non-sailing Day Rules
Boat Work and Race Committee days count toward awards with these limitations:

**Eligibility Requirements:**
- Maximum 5 non-sailing days per calendar year
- These 5 non-sailing day slots reset annually on January 1st
- No minimum sailing days required to use non-sailing days

**System Behavior:**
- Non-sailing day options are available to all users from the start of the year
- System displays clear messaging about remaining non-sailing days (e.g., "3 of 5 non-sailing days remaining")
- After 5 non-sailing days are used, non-sailing day options are hidden/disabled until next year
- Users can delete non-sailing day entries if needed, freeing up slots for new entries

### 2.4 Activity History
- Users can view all their previously entered activities
- Activities displayed in reverse chronological order (most recent first)
- All logged activities count toward awards (since ineligible ones cannot be logged)
- Current year activities can be edited or deleted
- Previous years' activities are read-only (view only)
- Activity history shows:
    - Date
    - Activity type (sailing, maintenance, or race committee)
    - Optional details when provided (location, sail number, notes)
- Ability to filter or search activities by optional fields (future enhancement)

---

## 3. Awards & Recognition System

### 3.1 Award Tiers
Participants earn recognition at the following annual milestones:
- **10 days**: First tier award
- **25 days**: Second tier award
- **50+ days**: Third tier award (including Burgee eligibility)

### 3.2 Award Counting Rules
- Qualifying days = Sailing Days + Logged Non-sailing Days
- All logged activities count toward awards
- Maximum 5 non-sailing days per calendar year
- Awards are based on calendar year (January 1 - December 31)
- All activities must be logged by January 31st of the following year (one-month grace period)

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

**Historical View:**
- Summary of each previous year's achievements
- Total days and awards earned per year
- Complete activity logs from prior years (read-only)

### 3.4 Award Administrator Notifications
Award Administrators need to know when users reach award thresholds to send recognition:
- Alert when any user reaches 10, 25, or 50 days
- Display participant information:
    - Full name (first and last)
    - Email address
    - Mailing address
    - Award tier reached
    - Date threshold was achieved
    - Current year total days

---

## 4. Dashboard & Reporting

### 4.1 User Dashboard
Display key metrics for current calendar year:
- Total qualifying days (toward awards)
- Number of sailing days
- Number of non-sailing days logged
- Non-sailing days remaining (X of 5 available)
- Progress bars showing advancement toward each tier (10, 25, 50 days)
- Awards earned this year
- Recent activity history with optional details displayed
- Reminder: "You must log your days by January 31st of the following year"

**Activity Entry Form:**
- Date picker (with duplicate date prevention)
- Activity type dropdown:
    - "Sailing on a Lightning" (always available)
    - "Boat/Trailer Maintenance - X of 5 remaining" (available while non-sailing days remain)
    - "Race Committee Work - X of 5 remaining" (available while non-sailing days remain)
- Helper text explaining when all non-sailing days have been used
- Optional fields section (collapsed/expandable for cleaner UX):
    - Location
    - Sail Number
    - Notes (free-form text area)

**Historical View:**
- Access to view previous years' data in read-only format
- Annual summary for each past year (total days, awards earned)
- Complete activity log from prior years (cannot be edited or deleted)

### 4.2 Leaderboards
Public leaderboards to encourage friendly competition and community engagement:

**Individual Leaderboard:**
- Rank sailors by total qualifying days for the current year
- Display: Rank, Name (or username), Days Logged, Fleet (optional), District (optional)
- Filterable by: All participants, specific district, specific fleet
- Top performers highlighted (e.g., Top 10, Top 25)
- **Tie-breaking rules** (in order of precedence):
  1. Total qualifying flashes (primary sort - descending)
  2. Sailing day count (more sailing days wins - descending)
  3. First entry timestamp (earliest entry wins - ascending)
  4. Alphabetical by first name, then last name

**Fleet Leaderboard:**
- Rank fleets by average days per member or total days logged by fleet
- Display: Rank, Fleet Number, Yacht Club, Total Days, Average Days/Member, Member Count
- Shows which fleets are most active
- Encourages fleet-level friendly competition
- **Tie-breaking rules** (in order of precedence):
  1. Total qualifying flashes (aggregated across all members - descending)
  2. Total sailing days (aggregated across all members - descending)
  3. Earliest first entry across all members (ascending)

**District Leaderboard:**
- Rank districts by average days per member or total days logged by district
- Display: Rank, District Name, Total Days, Average Days/Member, Member Count
- Shows which geographic regions are most active
- Encourages district-level participation
- **Tie-breaking rules** (in order of precedence):
  1. Total qualifying flashes (aggregated across all members - descending)
  2. Total sailing days (aggregated across all members - descending)
  3. Earliest first entry across all members (ascending)

**Leaderboard Updates:**
- Up-to-date leaderboards calculated on page load (low expected traffic volume)
- Historical view: See previous years' final standings

### 4.3 Award Administrator Dashboard
The award administrator view is specifically designed for award fulfillment purposes:
- List of users who have reached award thresholds (10, 25, or 50 days)
- For each award-eligible user, display:
    - Email address
    - Full name (first and last)
    - Mailing address
    - District (if provided)
    - Yacht Club (if provided)
    - Award tier reached
    - Date threshold was reached
- Ability to mark awards as "fulfilled" or "mailed" (optional feature)
- Filter to show only pending/unfulfilled awards
- Export list to CSV for mail merge or shipping labels
- Optional: View detailed activity logs for verification purposes

---

## 5. Technical Requirements

### 5.1 Platform
- Standalone web-based application (no external integrations required)
- Self-hosted on VPS or similar infrastructure
- Accessible via browser on desktop and mobile devices
- Responsive design for various screen sizes

### 5.2 Data Persistence
- User accounts and data must persist across sessions
- Secure storage of user credentials (bcrypt password hashing)
- Historical data retained indefinitely but locked from editing after year-end
- Single SQLite database file contains all application data

### 5.3 Data Integrity
- **One activity per date rule**: Users cannot log multiple activities on the same calendar date
- System must prevent duplicate date entries
- If user attempts to log an activity on a date that already has an entry, system should:
    - Alert the user that an activity already exists for that date
    - Allow user to view or edit the existing entry
    - Prevent creation of a duplicate entry

**Non-sailing day Eligibility Enforcement:**
- System calculates in real-time whether user has non-sailing days remaining
- Non-sailing day options are only shown/enabled when user has not used all 5 non-sailing day slots for current year
- If all 5 non-sailing days are used, non-sailing day options are hidden or disabled with clear explanation

### 5.4 Year-End Rollover
- Activity counts automatically reset to zero on January 1st
- Prior year data becomes read-only on February 1st (one-month grace period for late entries)
- Users can view and edit the previous year's activities until January 31st
- After January 31st, previous year data becomes view-only
- System maintains complete activity history across all years

### 5.5 Security
- Password protection for user accounts with secure hashing
- Secure authentication system
- Role-based access control (user vs. award administrator)
- HTTPS/SSL encryption for data transmission
- CSRF protection
- SQL injection prevention
- XSS protection

---

## 6. Future Considerations

### Potential Enhancements (Out of Scope for Initial Release)
- Email notifications to award administrators when users reach award thresholds
- Email notifications to users when they earn awards
- Award fulfillment tracking (marked as mailed/received)
- Export activity reports to PDF or CSV
---

## 7. Success Metrics

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

## 8. Open Questions for Stakeholders

1. **Name of Awards**: What are the specific names/types of awards for each tier (10, 25, 50 days)?
2. **Award Fulfillment Workflow**: Should award administrators be able to mark awards as "mailed" or "fulfilled" in the system to track completion?
3. **Branding**: What are the color scheme, logo, and branding requirements for the Lightning Class?
4. **Fleet and District Data**: Can the Lightning Class provide a list of fleets with their corresponding district mappings? This would enable dropdown selection and automatic district assignment based on fleet.
---

## Document Control

**Version**: 1.0  
**Last Updated**: October 10, 2025
