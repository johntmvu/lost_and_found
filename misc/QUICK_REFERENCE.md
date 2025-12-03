# 🎯 AI Matching System - Quick Reference

## Setup (One-Time)
```bash
cd /Users/John/Desktop/lost_and_found
./setup_ai_matching.sh
```

## Check System Status
```bash
./check_status.sh
```

## Add Test Data
```bash
mysql -u root campus_lost_found < test_data.sql
```

## Run Matching Algorithm

### Via Web
1. Log into application
2. Click **"🤖 Find Matches (AI)"** button

### Via Terminal
```bash
php run_matching.php
```

### Automated (Cron)
```bash
crontab -e
# Add this line:
0 * * * * cd /Users/John/Desktop/lost_and_found && php run_matching.php
```

## How to Use

### 1. Add Items
- Go to "Add Item" page
- Select:
  - **"Found Item"** if you found something
  - **"Lost Item"** if you lost something
- Fill in title, description, location

### 2. View Matches
- Click on any item card to open modal
- If you're the owner, see **"AI-Suggested Matches"** section
- View confidence score and reasoning

### 3. Take Action
- **✓ This is a Match** - Confirms match, enables contact
- **✗ Not a Match** - Dismisses suggestion
- **📧 Contact** - Email the other person

## File Reference

| File | Purpose |
|------|---------|
| `match_engine.php` | Core matching algorithms |
| `run_matching.php` | Execute matching process |
| `view_items.php` | Display matches in modals |
| `create_matches_table.sql` | Database schema |
| `add_item_type.sql` | Item type migration |
| `test_data.sql` | Sample data for testing |

## Confidence Scores

| Score | Color | Meaning |
|-------|-------|---------|
| 70-100% | 🟢 Green | High confidence match |
| 50-69% | 🟠 Orange | Medium confidence |
| 30-49% | ⚪ Gray | Low confidence |
| <30% | - | Not shown (filtered out) |

## Matching Factors

| Factor | Weight | What It Measures |
|--------|--------|------------------|
| Title | 40% | Item name similarity |
| Description | 30% | Detailed info similarity |
| Location | 20% | Where found/lost |
| Time | 10% | When posted (recency) |

## Algorithms Used

1. **similar_text()** - Character-level matching
2. **levenshtein()** - Edit distance
3. **Word Matching** - Common significant words
4. **metaphone()** - Phonetic similarity

## Troubleshooting

### No matches appearing?
- Run `./check_status.sh` to verify setup
- Ensure items exist with both types (found & lost)
- Click "🤖 Find Matches" to generate matches
- Check confidence threshold (minimum 30%)

### Database errors?
```bash
# Re-run migrations
mysql -u root campus_lost_found < add_item_type.sql
mysql -u root campus_lost_found < create_matches_table.sql
```

### PHP errors?
```bash
# Check syntax
php -l match_engine.php
php -l run_matching.php
php -l view_items.php
```

## Performance Tips

- First run may be slow (comparing all items)
- Subsequent runs skip existing matches
- Use cron for periodic background matching
- Dismiss false matches to keep UI clean

## Key Features

✅ Multi-algorithm text similarity  
✅ Weighted confidence scoring  
✅ Location and time awareness  
✅ Human-readable reasoning  
✅ Interactive match management  
✅ Direct contact integration  
✅ Automated batch processing  
✅ Duplicate prevention  

---

**Need more details?** See `AI_MATCHING_README.md`  
**Implementation overview?** See `IMPLEMENTATION_SUMMARY.md`
