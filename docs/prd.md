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
- Password reset capability

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
Users log activities using a simple form:

**Date Selection:**
- Multi-date selection: Users can select multiple dates at once using an interactive calendar
- Single-date edit mode available for editing existing entries
- All selected dates share the same activity details (type, location, notes)
- Duplicate prevention: existing entries cannot be re-selected
- Date restrictions: No future dates, respects grace period boundaries
- All-or-nothing validation: if any selected date has an error, no entries are created

**Activity Type (Required):**
- **Sailing on a Lightning** (always available, unlimited)
- **Boat/Trailer Maintenance** (non-sailing day - up to 5 per year count toward awards)
- **Race Committee Work** (non-sailing day - up to 5 per year count toward awards)

**Sailing Type (Required for sailing activities only):**
- Regatta, Club Race, Practice, or Day Sailing
- Purpose: Helps Lightning Class understand activity patterns for planning
- Cannot be specified for non-sailing activities

**Optional Fields:**
- Location (city, lake, venue)
- Sail Number (numeric)
- Notes (free-form text)

**Non-sailing Day Rules:**
- Maximum 5 non-sailing days per year count toward awards (resets January 1st)
- No minimum sailing days required to log non-sailing days
- Users can log unlimited non-sailing days, but only first 5 count toward awards
- System displays remaining non-sailing days counter (e.g., "3 of 5 remaining")
- Warning shown when logging 6th+ non-sailing day that won't count toward awards
- Encourages complete activity tracking while maintaining award integrity

**Edit Restrictions:**
- Current year activities: editable/deletable anytime
- Previous year activities: editable/deletable during January only (grace period)
- Starting February 1st: previous year becomes read-only
- Grace period deadline: All activities must be logged by January 31st of following year

**Date Restrictions:**
- No future dates allowed (tolerance: +1 day for timezone handling)
- No duplicate dates per user
- During January: can select current year + previous year dates
- Starting February 1st: can only select current year dates

### 2.4 Activity History
- Users can view all their previously entered activities
- Activities displayed in reverse chronological order (most recent first)
- All logged activities count toward awards (since ineligible ones cannot be logged)
- Current year activities can be edited or deleted
- Previous year activities can be edited or deleted during January grace period
- Previous years' activities become read-only starting February 1st (view only)
- Activity history shows:
    - Date
    - Activity type (sailing, maintenance, or race committee)
    - Optional details when provided (location, sail number, notes)
- Ability to filter or search activities by optional fields (future enhancement)

**Activity Ordering:**
- Activities are ordered by the activity date (the date the activity occurred), not by when the entry was created
- Example: If a user logs last week's sailing trip today, it appears in chronological position based on last week's date, not at the top as today's entry

**"Just Logged" Badge:**
- Recently created entries display a "Just logged" badge for visibility
- Badge appears when the entry's creation timestamp (created_at) is today
- Badge is based on when the entry was logged, not when the activity occurred
- Example: Logging last week's activity today shows "Just logged" badge even though the activity was last week
- This helps users quickly identify which entries they just added during the current session

---

## 3. Awards & Recognition System

### 3.1 Award Tiers
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

**Historical View:**
- Summary of each previous year's achievements
- Total days and awards earned per year
- Complete activity logs from prior years (read-only)
- Only current year activities count toward current year awards

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
The user dashboard is the main landing page after login, displaying:
- Current year progress metrics (see Section 3.3 Progress Tracking for details)
- Activity entry form (see Section 2.2 Activity Entry for form details)
- Recent activity history with edit/delete capabilities (see Section 2.4 Activity History)
- Grace period reminder: "You must log your days by January 31st of the following year"

### 4.2 Leaderboards
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

## 5. System Requirements

### 5.1 Platform
- Web-based application accessible on desktop and mobile devices
- Self-hosted deployment
- Responsive design for various screen sizes

### 5.2 Data Management
- User accounts and activity data persist across sessions
- Historical data retained indefinitely (read-only after grace period)
- One activity per date per user (duplicate prevention enforced)

### 5.3 Security
- Secure user authentication and password protection
- Role-based access control (regular users and award administrators)
- Industry-standard security practices

*For technical architecture and implementation details, see [README.md](../README.md) and [CONTRIBUTING.md](CONTRIBUTING.md).*

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
