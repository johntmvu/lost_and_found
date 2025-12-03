<?php
/**
 * Run Matching Algorithm
 * This script finds potential matches between lost and found items
 * Can be run manually or via cron job
 */

require_once 'db_connect.php';
require_once 'match_engine.php';

// Check if running from command line
$isCLI = php_sapi_name() === 'cli';

if (!$isCLI) {
    // If running from web, require authentication
    session_start();
    if (!isset($_SESSION['user_id'])) {
        die('Access denied. Please log in.');
    }
}

// Initialize matcher
$matcher = new ItemMatcher($conn);

echo $isCLI ? "Starting matching algorithm...\n" : "<h2>Running AI Matching Algorithm</h2>";

// Run the matching
$result = $matcher->findAllMatches();

// Display results
if ($isCLI) {
    echo "✓ Complete!\n";
    echo "  - New matches created: {$result['matches_created']}\n";
    echo "  - Existing matches skipped: {$result['matches_skipped']}\n";
} else {
    echo "<p>✓ <strong>Complete!</strong></p>";
    echo "<ul>";
    echo "<li>New matches created: <strong>{$result['matches_created']}</strong></li>";
    echo "<li>Existing matches skipped: <strong>{$result['matches_skipped']}</strong></li>";
    echo "</ul>";
    echo "<p><a href='view_items.php'>← Back to Items</a></p>";
}

// Close connection
$conn->close();
