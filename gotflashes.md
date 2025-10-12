# GOT-FLASHES Challenge Tracker
## Product Requirements Document

### Executive Summary
A standalone web-based application to replace the current web form for tracking participant days sailing Lightnings as part of the GOT-FLASHES Challenge. The system tracks annual sailing activity and automatically identifies award eligibility (10, 25, or 50+ days), with the goal of encouraging Lightning sailors to get on the water more frequently and creating friendly competition within fleets and districts.

---

## 1. User Management

### 1.1 User Registration
New users can create accounts with the following information:
- Email address (serves as unique username/identifier)
- Password (secure authentication)
- First Name
- Last Name
- Date of Birth (for demographics and membership growth analytics, particularly tracking participation of sailors under 32)
- Physical mailing address
- District (optional)
- Yacht Club (optional)

**Note**: Users do not need to be Lightning owners - crews and anyone who sails on Lightnings can participate.

### 1.2 User Authentication
- Secure login system using email and password
- Session persistence to keep users logged in across visits
- Logout functionality
- Password reset capability

### 1.3 Profile Management
- Users can view their profile information
- Users can update their address, district, yacht club, and date of birth
- Email address should remain fixed after registration (serves as permanent identifier)

### 1.4 User Roles
- **Regular Users**: Can track their own activities and view their progress
- **Award Administrators**: Responsible for viewing award eligibility and mailing physical awards to participants

---

## 2. Activity Tracking

### 2.1 Program Rules - What Counts
The following activities count toward GOT-FLASHES awards:

**Sailing Days:**
- Any time spent sailing on ANY Lightning (not just your own boat)
- Counts whether you're the skipper or crew
- Even one hour on a Lightning counts as a full "day"
- Goal: Get the boat off the dock!

**Boat Work Days (Freebies):**
After reaching the first 10-day tier, the following activities may count:
- Lightning boat maintenance
- Lightning trailer maintenance
- On-the-water Race Committee work on days where Lightnings are racing
- **Limitation**: Maximum 5 "freebie" days total per calendar year

### 2.2 Activity Entry
Users log activities using a simple form for each day:
- Date of activity (cannot duplicate an existing entry date)
- Activity type (dynamically shown based on eligibility):
    - **Sailing on a Lightning** - Always available
    - **Boat/Trailer Maintenance (freebie)** - Only available when conditions are met
    - **Race Committee Work (freebie)** - Only available when conditions are met
- **Optional fields** (enhance tracking and create richer records):
    - Yacht Club
    - Fleet Number
    - Regatta Name
    - Location (city, lake, venue)
    - Sail Number
    - Notes (free-form text)

**Freebie Entry Restrictions:**
The system will only allow users to log freebie activities when they are eligible to count:
- Freebie options are hidden/disabled until user reaches 10 sailing days
- Once 10 sailing days reached, freebies become available
- System displays remaining freebies (e.g., "3 of 5 freebies remaining")
- After 5 freebies are used, freebie options are hidden/disabled again
- Clear messaging explains why freebies are unavailable

**Benefits of this approach:**
- Users never log activities that don't count
- Eliminates confusion about "logged but didn't count" scenarios
- Simpler logic and fewer edge cases
- Better user experience with clear eligibility status

**Edit Restrictions:**
- Users can edit or delete activities from the current calendar year only
- Activities from previous years are view-only and cannot be modified
- Users cannot create multiple activities for the same date
- All activities must be logged by December 31st of the year in which they occurred
- Deleting sailing days may cause total to drop below 10, which could affect freebie eligibility

### 2.3 Freebie Day Rules
Boat Work and Race Committee days count toward awards with these limitations:

**Eligibility Requirements:**
- User must have at least 10 sailing days in the current year
- Maximum 5 freebie days per calendar year
- These 5 freebie slots reset annually on January 1st

**System Behavior:**
- System prevents logging freebies when ineligible (rather than logging them as "didn't count")
- If user has fewer than 10 sailing days, freebie options are not available
- If user has used all 5 freebies, freebie options are not available
- System displays clear messaging about eligibility status and remaining freebies
- If user deletes sailing days and drops below 10 total, existing freebies remain valid but new ones cannot be added until 10 sailing days are reached again

### 2.4 Activity History
- Users can view all their previously entered activities
- Activities displayed in reverse chronological order (most recent first)
- All logged activities count toward awards (since ineligible ones cannot be logged)
- Current year activities can be edited or deleted
- Previous years' activities are read-only (view only)
- Activity history shows:
    - Date
    - Activity type (sailing, maintenance, or race committee)
    - Optional details when provided (yacht club, fleet, regatta, location, sail number, notes)
- Ability to filter or search activities by optional fields (future enhancement)

---

## 3. Awards & Recognition System

### 3.1 Award Tiers
Participants earn recognition at the following annual milestones:
- **10 days**: First tier award
- **25 days**: Second tier award
- **50+ days**: Third tier award (including Burgee eligibility)

### 3.2 Award Counting Rules
- Qualifying days = Sailing Days + Logged Freebie Days
- All logged activities count toward awards (system prevents logging ineligible freebies)
- Freebies only become available after reaching 10 sailing days
- Maximum 5 freebie days per calendar year
- Awards are based on calendar year (January 1 - December 31)
- All activities must be logged by December 31st to count for that year

### 3.3 Progress Tracking
Users should see their current annual progress:
- Total sailing days (current year)
- Total freebie days logged
- Freebies remaining (X of 5 available) - only shown when at least 10 sailing days
- Total qualifying days (toward awards)
- Visual progress indicators toward each award tier (10, 25, 50)
- Awards earned in current year
- Which award tier they've achieved
- Clear messaging about freebie eligibility:
    - If below 10 sailing days: "Freebies available after reaching 10 sailing days"
    - If at/above 10 with freebies left: "3 of 5 freebies remaining"
    - If all 5 used: "All 5 freebies used this year"

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
- Number of freebie days logged
- Freebies remaining (if eligible) or eligibility status message
- Progress bars showing advancement toward each tier (10, 25, 50 days)
- Awards earned this year
- Recent activity history with optional details displayed
- Reminder: "You must log your days by December 31"

**Activity Entry Form:**
- Date picker (with duplicate date prevention)
- Activity type dropdown that dynamically shows:
    - "Sailing on a Lightning" (always available)
    - "Boat/Trailer Maintenance - X of 5 remaining" (only when eligible)
    - "Race Committee Work - X of 5 remaining" (only when eligible)
- Helper text explaining freebie eligibility when not available
- Optional fields section (collapsed/expandable for cleaner UX):
    - Yacht Club
    - Fleet Number
    - Regatta Name
    - Location
    - Sail Number
    - Notes (free-form text area)

**Historical View:**
- Access to view previous years' data in read-only format
- Annual summary for each past year (total days, awards earned)
- Complete activity log from prior years (cannot be edited or deleted)

### 4.2 Award Administrator Dashboard
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

### 5.1 Technology Stack

**Backend Framework:**
- Laravel (latest stable version)
- PHP 8.1 or higher

**Database:**
- SQLite with Write-Ahead Logging (WAL) mode enabled
- No separate database server required
- Database stored as single file for easy backup

**Frontend:**
- Bootstrap 5 for responsive UI components
- Blade templating engine (Laravel's built-in)
- Vanilla JavaScript for client-side interactions

**Web Server:**
- Nginx as reverse proxy and web server
- PHP-FPM for processing PHP requests

**Security:**
- Let's Encrypt SSL/TLS certificates for HTTPS
- Automatic certificate renewal via certbot

**Authentication:**
- Laravel Breeze for user authentication scaffolding
- Includes registration, login, password reset, and profile management

### 5.2 Platform
- Standalone web-based application (no external integrations required)
- Self-hosted on VPS or similar infrastructure
- Accessible via browser on desktop and mobile devices
- Responsive design for various screen sizes

### 5.3 Data Persistence
- User accounts and data must persist across sessions
- Secure storage of user credentials (bcrypt password hashing)
- Historical data retained indefinitely but locked from editing after year-end
- Single SQLite database file contains all application data

### 5.4 Data Integrity
- **One activity per date rule**: Users cannot log multiple activities on the same calendar date
- System must prevent duplicate date entries
- If user attempts to log an activity on a date that already has an entry, system should:
    - Alert the user that an activity already exists for that date
    - Allow user to view or edit the existing entry
    - Prevent creation of a duplicate entry

**Freebie Eligibility Enforcement:**
- System calculates in real-time whether user is eligible to log freebies
- Freebie options are only shown/enabled when:
    - User has at least 10 sailing days in current year, AND
    - User has not used all 5 freebie slots for current year
- If conditions not met, freebie options are hidden or disabled with clear explanation

### 5.5 Year-End Rollover
- Activity counts automatically reset to zero on January 1st
- Prior year data becomes read-only (view only, no edits)
- Users can view historical data from previous years but cannot modify it
- System maintains complete activity history across all years

### 5.6 Security
- Password protection for user accounts (bcrypt hashing)
- Secure authentication system via Laravel Breeze
- Role-based access control (user vs. award administrator)
- HTTPS/SSL encryption for data transmission via Let's Encrypt
- CSRF protection (Laravel built-in)
- SQL injection prevention (Laravel ORM/Eloquent)
- XSS protection (Blade template escaping)

---

## 6. Future Considerations

### Potential Enhancements (Out of Scope for Initial Release)
- Email notifications to award administrators when users reach award thresholds
- Email notifications to users when they earn awards
- Award fulfillment tracking (marked as mailed/received)
- Export activity reports to PDF or CSV
- Social features (leaderboards, community activity feed)

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
3. **District and Yacht Club**: Should we provide a predefined list of districts and yacht clubs, or allow free-form text entry?
4. **Year-End Timing**: Should the system lock prior year data at midnight January 1st, or allow a grace period for late entries from December?
5. **Deleting Sailing Days**: If a user deletes sailing days and drops below 10 total, should their existing freebies remain valid or be removed?
6. **Branding**: What are the color scheme, logo, and branding requirements for the Lightning Class?
7. **Launch Timeline**: What is the target go-live date?
---

## Document Control

**Version**: 1.0  
**Last Updated**: October 10, 2025