#!/bin/bash
# Quick status check for AI Matching System

echo "🔍 AI Matching System Status Check"
echo "===================================="
echo ""

# Check if required files exist
echo "📁 Files:"
files=("match_engine.php" "run_matching.php" "create_matches_table.sql" "add_item_type.sql")
for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        echo "  ✅ $file"
    else
        echo "  ❌ $file (missing)"
    fi
done
echo ""

# Check PHP syntax
echo "🔧 PHP Syntax:"
for phpfile in match_engine.php run_matching.php view_items.php add_item.php search.php; do
    if [ -f "$phpfile" ]; then
        result=$(php -l "$phpfile" 2>&1)
        if [[ $result == *"No syntax errors"* ]]; then
            echo "  ✅ $phpfile"
        else
            echo "  ❌ $phpfile has errors"
        fi
    fi
done
echo ""

# Check MySQL (if available)
if command -v mysql &> /dev/null; then
    echo "🗄️  Database Check:"
    
    # Check if ItemMatch table exists
    table_check=$(mysql -u root -s -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'campus_lost_found' AND table_name = 'ItemMatch';" 2>&1)
    
    if [[ $table_check == "1" ]]; then
        echo "  ✅ ItemMatch table exists"
        
        # Count matches
        match_count=$(mysql -u root -s -N -e "SELECT COUNT(*) FROM campus_lost_found.ItemMatch;" 2>&1)
        echo "  📊 Total matches in database: $match_count"
        
        # Count by status
        pending=$(mysql -u root -s -N -e "SELECT COUNT(*) FROM campus_lost_found.ItemMatch WHERE status='pending';" 2>&1)
        confirmed=$(mysql -u root -s -N -e "SELECT COUNT(*) FROM campus_lost_found.ItemMatch WHERE status='confirmed';" 2>&1)
        dismissed=$(mysql -u root -s -N -e "SELECT COUNT(*) FROM campus_lost_found.ItemMatch WHERE status='dismissed';" 2>&1)
        
        echo "    ├─ Pending: $pending"
        echo "    ├─ Confirmed: $confirmed"
        echo "    └─ Dismissed: $dismissed"
    else
        echo "  ⚠️  ItemMatch table not found"
        echo "     Run: mysql -u root campus_lost_found < create_matches_table.sql"
    fi
    
    # Check item_type column
    col_check=$(mysql -u root -s -N -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'campus_lost_found' AND table_name = 'Item' AND column_name = 'item_type';" 2>&1)
    
    if [[ $col_check == "1" ]]; then
        echo "  ✅ Item.item_type column exists"
        
        # Count items by type
        found_count=$(mysql -u root -s -N -e "SELECT COUNT(*) FROM campus_lost_found.Item WHERE item_type='found';" 2>&1)
        lost_count=$(mysql -u root -s -N -e "SELECT COUNT(*) FROM campus_lost_found.Item WHERE item_type='lost';" 2>&1)
        
        echo "  📦 Items in database:"
        echo "    ├─ Found: $found_count"
        echo "    └─ Lost: $lost_count"
    else
        echo "  ⚠️  Item.item_type column not found"
        echo "     Run: mysql -u root campus_lost_found < add_item_type.sql"
    fi
else
    echo "⚠️  MySQL not found in PATH, skipping database checks"
fi

echo ""
echo "===================================="
echo "📋 Quick Actions:"
echo "  • Run matching: php run_matching.php"
echo "  • Add test data: mysql -u root campus_lost_found < test_data.sql"
echo "  • View in browser: Open view_items.php"
echo ""
