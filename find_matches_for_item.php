<?php
/**
 * Find Matches for a Single Item
 * Run matching algorithm for just one specific item
 */

session_start();
require_once 'db_connect.php';
require_once 'match_engine.php';

$session_user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$item_id = intval($_GET['item_id'] ?? 0);

if (!$session_user_id || !$item_id) {
    header('Location: view_items.php');
    exit;
}

// Verify user owns this item
$check = $conn->prepare("SELECT user_id FROM Posts WHERE item_id = ? LIMIT 1");
$check->bind_param('i', $item_id);
$check->execute();
$check_result = $check->get_result();

if (!$check_result || $check_result->num_rows === 0) {
    header('Location: view_items.php');
    exit;
}

$check_row = $check_result->fetch_assoc();
if (intval($check_row['user_id']) !== $session_user_id) {
    header('Location: view_items.php');
    exit;
}

// Get item details
$item_stmt = $conn->prepare("SELECT item_type FROM Item WHERE item_id = ?");
$item_stmt->bind_param('i', $item_id);
$item_stmt->execute();
$item_result = $item_stmt->get_result();
$item_data = $item_result->fetch_assoc();
$item_type = $item_data['item_type'] ?? 'found';

// Initialize matcher
$matcher = new ItemMatcher($conn);

// Get the target item
$target_sql = "SELECT Item.item_id, Item.title, Item.description, Item.created_at,
                      Location.building, Location.room
               FROM Item
               LEFT JOIN At ON Item.item_id = At.item_id
               LEFT JOIN Location ON At.location_id = Location.location_id
               WHERE Item.item_id = ?";
$target_stmt = $conn->prepare($target_sql);
$target_stmt->bind_param('i', $item_id);
$target_stmt->execute();
$target_result = $target_stmt->get_result();
$target_item = $target_result->fetch_assoc();

if (!$target_item) {
    header('Location: view_items.php');
    exit;
}

// Get all comparison items (opposite type)
$compare_type = $item_type === 'lost' ? 'found' : 'lost';
$compare_sql = "SELECT Item.item_id, Item.title, Item.description, Item.created_at,
                       Location.building, Location.room
                FROM Item
                LEFT JOIN At ON Item.item_id = At.item_id
                LEFT JOIN Location ON At.location_id = Location.location_id
                WHERE Item.item_type = ? AND Item.status = 'available' AND Item.item_id != ?";
$compare_stmt = $conn->prepare($compare_sql);
$compare_stmt->bind_param('si', $compare_type, $item_id);
$compare_stmt->execute();
$compare_result = $compare_stmt->get_result();

$matches_created = 0;
$matches_skipped = 0;

// Compare with each item
while ($compare_item = $compare_result->fetch_assoc()) {
    // Calculate match
    if ($item_type === 'lost') {
        $match = $matcher->calculateMatch($target_item, $compare_item);
        $lost_id = $item_id;
        $found_id = $compare_item['item_id'];
    } else {
        $match = $matcher->calculateMatch($compare_item, $target_item);
        $lost_id = $compare_item['item_id'];
        $found_id = $item_id;
    }
    
    // Only save matches with 50% or higher confidence
    if ($match['confidence'] >= 50.0) {
        // Check if match already exists
        $check_stmt = $conn->prepare(
            "SELECT match_id FROM ItemMatch WHERE lost_item_id = ? AND found_item_id = ?"
        );
        $check_stmt->bind_param('ii', $lost_id, $found_id);
        $check_stmt->execute();
        $existing = $check_stmt->get_result();
        
        if ($existing->num_rows === 0) {
            // Insert new match
            $insert_stmt = $conn->prepare(
                "INSERT INTO ItemMatch (lost_item_id, found_item_id, confidence_score, match_reasoning) 
                 VALUES (?, ?, ?, ?)"
            );
            $insert_stmt->bind_param('iids', $lost_id, $found_id, $match['confidence'], $match['reasoning']);
            
            if ($insert_stmt->execute()) {
                $matches_created++;
            }
        } else {
            $matches_skipped++;
        }
    }
}

$conn->close();

// Redirect back with success message
header("Location: view_items.php?matches_found={$matches_created}");
exit;
