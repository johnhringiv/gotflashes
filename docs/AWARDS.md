# Award Icons

This document describes the award system and placeholder icons used in the G.O.T. Flashes Challenge Tracker.

## Award Tiers

The application has three award tiers based on annual sailing activity:

| Tier | Days Required | Current Icon | Color | Description |
|------|---------------|--------------|-------|-------------|
| Bronze | 10 days | 🏆 | #CD7F32 | 10 Day Award |
| Silver | 25 days | 🏆 | #C0C0C0 | 25 Day Award |
| Gold | 50+ days | 🏆 | #FFD700 | 50 Day Award (Burgee) |

## Current Implementation

**Location**: `resources/views/flashes/index.blade.php` (lines 18-46)

The awards use **Bootstrap Icons** `bi-trophy-fill` SVG with custom colors:
- **Bronze** (10 days): Trophy filled with #CD7F32 (bronze)
- **Silver** (25 days): Trophy filled with #C0C0C0 (silver)
- **Gold** (50 days): Trophy filled with #FFD700 (gold)

Awards are displayed as DaisyUI badges with tooltips showing the award name.

### SVG Implementation
```blade
<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="{{ $color }}" class="bi bi-trophy-fill" viewBox="0 0 16 16">
    <path d="M2.5.5A.5.5 0 0 1 3 0h10a.5.5 0 0 1 .5.5q0 .807-.034 1.536a3 3 0 1 1-1.133 5.89c-.79 1.865-1.878 2.777-2.833 3.011v2.173l1.425.356c.194.048.377.135.537.255L13.3 15.1a.5.5 0 0 1-.3.9H3a.5.5 0 0 1-.3-.9l1.838-1.379c.16-.12.343-.207.537-.255L6.5 13.11v-2.173c-.955-.234-2.043-1.146-2.833-3.012a3 3 0 1 1-1.132-5.89A33 33 0 0 1 2.5.5m.099 2.54a2 2 0 0 0 .72 3.935c-.333-1.05-.588-2.346-.72-3.935m10.083 3.935a2 2 0 0 0 .72-3.935c-.133 1.59-.388 2.885-.72 3.935"/>
</svg>
```

## Award Logic

**Display Rules**:
- Awards are cumulative - earning a higher tier keeps lower tier badges visible
- Example: At 50 days, user sees all three awards: 🥉 10, 🥈 25, 🥇 50
- Awards only appear once the threshold is met (not before)
- Awards are calculated per calendar year (resets January 1st)

**Calculation**:
- Total qualifying days = sailing days + non-sailing days (capped at 5)
- Controller: `app/Http/Controllers/FlashController.php` (lines 38-49)
- View data: `$earnedAwards` array contains milestone numbers (e.g., [10, 25, 50])

## Replacing with Custom Icons

To replace emoji placeholders with custom images:

### Option 1: Using Image Files

1. Add image files to `public/images/awards/`:
   - `bronze-10.png` (or .svg)
   - `silver-25.png` (or .svg)
   - `gold-50.png` (or .svg)

2. Update the view (`resources/views/flashes/index.blade.php`):

```blade
@php
    $icon = match($award) {
        10 => '<img src="' . asset('images/awards/bronze-10.png') . '" alt="Bronze" class="w-6 h-6">',
        25 => '<img src="' . asset('images/awards/silver-25.png') . '" alt="Silver" class="w-6 h-6">',
        50 => '<img src="' . asset('images/awards/gold-50.png') . '" alt="Gold" class="w-6 h-6">',
        default => '🏅'
    };
@endphp
<span class="text-xl">{!! $icon !!}</span>
```

### Option 2: Using SVG Inline

Inline SVG provides better control and scalability:

```blade
@php
    $icon = match($award) {
        10 => '<svg class="w-6 h-6"><!-- Bronze SVG path --></svg>',
        25 => '<svg class="w-6 h-6"><!-- Silver SVG path --></svg>',
        50 => '<svg class="w-6 h-6"><!-- Gold SVG path --></svg>',
        default => '🏅'
    };
@endphp
<span>{!! $icon !!}</span>
```

### Option 3: Using Icon Library

If using an icon library like Font Awesome or Heroicons:

```blade
@php
    $iconClass = match($award) {
        10 => 'fa-medal text-orange-600',
        25 => 'fa-medal text-gray-400',
        50 => 'fa-medal text-yellow-500',
        default => 'fa-award'
    };
@endphp
<i class="fas {{ $iconClass }} text-xl"></i>
```

## Design Recommendations

When creating custom award icons:

1. **Size**: Design at 64x64px minimum for crisp display
2. **Format**: SVG preferred for scalability, PNG acceptable
3. **Colors**:
   - Bronze: #CD7F32 or similar warm orange/brown
   - Silver: #C0C0C0 or similar cool gray
   - Gold: #FFD700 or similar bright yellow/gold
4. **Style**: Should complement the existing DaisyUI design system
5. **Accessibility**: Include alt text for screen readers

## Testing

After replacing icons, test at different milestones:

```bash
# Test each tier
APP_ENV=testing php artisan test --filter=FlashProgressTest

# Specific tests:
# - test_bronze_award_shown_at_10_days
# - test_silver_award_shown_at_25_days
# - test_gold_award_shown_at_50_days
```

## Future Enhancements

Potential improvements to the award system:

- **Award Certificates**: Downloadable PDF certificates for each tier
- **Award History**: View past years' earned awards
- **Social Sharing**: Share achievement badges on social media
- **Physical Awards**: Integration with Lightning Class Association for physical burgee/plaque orders
- **Animation**: Celebrate when awards are first earned with confetti/animation
