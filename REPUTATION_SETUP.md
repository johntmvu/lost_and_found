# Reputation System Setup Guide

## Overview
The reputation & trust score system has been fully implemented with the following features:
- ✅ Point-based reputation scoring
- ✅ Trust level badges (Newcomer → Legendary)
- ✅ Achievement badges (12 different badges)
- ✅ User profiles with stats and activity logs
- ✅ Visual indicators in item cards and modals
- ✅ Automatic badge awards

## Database Migration

**IMPORTANT:** You must run the database migration before testing the reputation system.

### Option 1: MySQL Command Line
```bash
mysql -u root -p campus_lost_found < add_reputation_system.sql
```

### Option 2: phpMyAdmin
1. Open phpMyAdmin
2. Select the `campus_lost_found` database
3. Click on the "SQL" tab
4. Copy and paste the entire contents of `add_reputation_system.sql`
5. Click "Go" to execute

### Option 3: MySQL Workbench
1. Open MySQL Workbench
2. Connect to your database server
3. Open `add_reputation_system.sql`
4. Execute the script

## Reputation Points

### Point Values
- **Post Item**: +5 points
- **Submit Claim**: +2 points
- **Claim Approved**: +10 points (awarded to claimant)
- **Claim Rejected**: -5 points (deducted from claimant)
- **Item Returned**: +20 points (awarded to item owner)
- **Match Confirmed**: +15 points (awarded to user who confirms)
- **Account Verified**: +25 points (manual admin action)

### Trust Levels
| Level | Reputation Score | Color | Icon |
|-------|------------------|-------|------|
| Newcomer | 0+ | #95a5a6 (Gray) | 🌱 |
| Active | 20+ | #3498db (Blue) | ⚡ |
| Trusted | 50+ | #9b59b6 (Purple) | ⭐ |
| Highly Trusted | 100+ | #e67e22 (Orange) | 💎 |
| Elite | 250+ | #e74c3c (Red) | 👑 |
| Legendary | 500+ | #f39c12 (Gold) | 🏆 |

## Achievement Badges

### Available Badges
1. **Newcomer** (🌱) - Welcome to the platform
2. **First Post** (📝) - Post your first item
3. **Helper** (🤝) - Submit 5 claims
4. **Trusted** (⭐) - Reach 50 reputation
5. **Reliable** (💯) - Achieve 80% success rate with 5+ returns
6. **Frequent Finder** (🔍) - Post 10 items
7. **Elite** (👑) - Reach 250 reputation
8. **Match Maker** (🎯) - Confirm 10 AI matches
9. **Legendary** (🏆) - Reach 500 reputation
10. **Verified** (🎓) - Get your account verified (admin action)
11. **Veteran** (🗓️) - Member for 6+ months
12. **Super Star** (⭐⭐⭐) - Reach 1000 reputation

### Badge Levels
- Bronze border: Basic achievements
- Silver border: Intermediate achievements
- Gold border: Advanced achievements
- Red border: Elite achievements

## Features Implemented

### 1. Item Cards (Grid View)
- Trust level icon and badge next to poster name
- Color-coded trust levels
- Compact design for quick scanning

### 2. Item Detail Modals
- Full reputation display section with:
  - Large trust level icon
  - Clickable poster name (links to profile)
  - Verified badge (if applicable)
  - Trust level badge with color
  - Reputation score
- Styled in a card-like format for visual appeal

### 3. User Profile Page (`user_profile.php`)
- Gradient reputation score header
- Trust level badge display
- 4-column statistics grid:
  - Items Posted
  - Items Returned
  - Claims Approved
  - Success Rate
- Achievement badges showcase
- Recent activity log (10 most recent actions)
- Responsive design

### 4. Navigation
- "My Profile" link added to main navigation
- Easy access from any page

## Testing the System

### Step 1: Post an Item
1. Go to "Add Item"
2. Fill out the form and submit
3. Check your profile - you should have +5 reputation

### Step 2: Submit a Claim
1. Find an item in the grid
2. Click to open modal
3. Submit a claim with details
4. Check your profile - you should have +2 reputation

### Step 3: Approve a Claim (as item owner)
1. Post an item
2. Have another user claim it
3. Approve the claim
4. Claimant gets +10 reputation
5. Check if "First Post" and "Newcomer" badges were awarded

### Step 4: Mark as Returned
1. After approving a claim, mark the item as returned
2. You (the owner) get +20 reputation
3. Check for "Trusted" badge if you reached 50+ points

### Step 5: Confirm AI Match
1. Post a lost or found item
2. Click "Find AI Matches"
3. Confirm a match
4. You get +15 reputation

### Step 6: View Trust Level Progression
1. Accumulate points through various actions
2. Watch your trust level badge change color
3. See the icon change as you reach new levels
4. Check which new badges you've unlocked

## Files Modified/Created

### New Files
- `reputation_system.php` - Core reputation management class
- `user_profile.php` - User profile page
- `add_reputation_system.sql` - Database migration
- `REPUTATION_SETUP.md` - This guide

### Modified Files
- `view_items.php` - Added reputation display and point awards
- `add_item.php` - Added reputation points for posting items
- `css/style.css` - Added reputation styling

## Troubleshooting

### Database Connection Issues
- Verify your MySQL credentials in `db_connect.php`
- Ensure the `campus_lost_found` database exists
- Check that you have CREATE TABLE and ALTER TABLE permissions

### Reputation Not Updating
- Clear browser cache
- Check browser console for JavaScript errors
- Verify the database migration ran successfully
- Check that `reputation_system.php` is being included

### Badges Not Appearing
- Ensure the `Badge` and `UserBadge` tables were created
- Check that the default badge insert queries ran
- Verify the badge icons are displaying (they use emoji)

### Profile Page Issues
- Confirm user is logged in (session active)
- Check that user_id is being passed correctly
- Verify SQL queries have proper user_id filtering

## Future Enhancements

Potential additions for the reputation system:
- Admin panel to manually adjust reputation
- Badge creation interface for custom achievements
- Leaderboard page showing top users
- Reputation decay for inactive users
- Bonus multipliers for verified accounts
- Weekly/monthly challenges with bonus points
- Reputation required for certain actions (spam prevention)
- User reputation comparison on profile pages
- Notification system for badge unlocks
- Reputation history graph/chart

## Notes

- Reputation scores can go negative if users have many rejected claims
- Badge unlocking is automatic when criteria are met
- Trust levels provide visual trust indicators but don't grant special permissions yet
- The system is designed to encourage positive community behavior
- Verified status must be granted manually (future admin feature)
