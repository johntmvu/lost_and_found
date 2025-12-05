<?php
/**
 * AI-Powered Item Matching Engine
 * Compares lost items with found items to find potential matches
 */

require_once 'db_connect.php';

class ItemMatcher {
    private $conn;
    
    // Weights for different matching factors
    const WEIGHT_TITLE = 0.4;
    const WEIGHT_DESCRIPTION = 0.3;
    const WEIGHT_LOCATION = 0.2;
    const WEIGHT_TIME = 0.1;
    
    // Minimum confidence threshold to save a match
    const MIN_CONFIDENCE = 30.0;
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }
    
    /**
     * Calculate text similarity between two strings using multiple algorithms
     * Returns a score from 0 to 100
     */
    private function calculateTextSimilarity($text1, $text2) {
        if (empty($text1) || empty($text2)) {
            return 0;
        }
        
        $text1 = strtolower(trim($text1));
        $text2 = strtolower(trim($text2));
        
        // 1. Similar text algorithm (percentage of matching characters)
        similar_text($text1, $text2, $percent1);
        
        // 2. Levenshtein distance (for short strings)
        $maxLen = max(strlen($text1), strlen($text2));
        if ($maxLen <= 255 && $maxLen > 0) {
            $distance = levenshtein($text1, $text2);
            $percent2 = (1 - ($distance / $maxLen)) * 100;
        } else {
            $percent2 = $percent1; // fallback for long strings
        }
        
        // 3. Word-level matching (check for common significant words)
        $words1 = array_filter(explode(' ', $text1), function($w) { return strlen($w) > 3; });
        $words2 = array_filter(explode(' ', $text2), function($w) { return strlen($w) > 3; });
        
        if (count($words1) > 0 && count($words2) > 0) {
            $commonWords = count(array_intersect($words1, $words2));
            $totalWords = max(count($words1), count($words2));
            $percent3 = ($commonWords / $totalWords) * 100;
        } else {
            $percent3 = 0;
        }
        
        // 4. Metaphone (phonetic similarity)
        $metaphone1 = metaphone($text1);
        $metaphone2 = metaphone($text2);
        similar_text($metaphone1, $metaphone2, $percent4);
        
        // Average all methods for final score
        return ($percent1 * 0.3 + $percent2 * 0.3 + $percent3 * 0.3 + $percent4 * 0.1);
    }
    
    /**
     * Calculate location proximity score
     * Returns 0-100 based on how similar locations are
     */
    private function calculateLocationSimilarity($loc1, $loc2) {
        if (empty($loc1) || empty($loc2)) {
            return 50; // neutral score if location unknown
        }
        
        $loc1 = strtolower(trim($loc1));
        $loc2 = strtolower(trim($loc2));
        
        // Exact match
        if ($loc1 === $loc2) {
            return 100;
        }
        
        // Check if one location contains the other (same building)
        if (strpos($loc1, $loc2) !== false || strpos($loc2, $loc1) !== false) {
            return 80;
        }
        
        // Use text similarity for partial matches
        similar_text($loc1, $loc2, $percent);
        return $percent;
    }
    
    /**
     * Calculate time proximity score
     * Items posted closer in time are more likely to match
     */
    private function calculateTimeProximity($lostItemId, $foundItemId) {
        // Get timestamps (assuming Item has created_at or use Posts table)
        $sql = "SELECT 
                    (SELECT created_at FROM Item WHERE item_id = ?) as lost_time,
                    (SELECT created_at FROM Item WHERE item_id = ?) as found_time";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ii', $lostItemId, $foundItemId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $lostTime = strtotime($row['lost_time']);
            $foundTime = strtotime($row['found_time']);
            
            if (!$lostTime || !$foundTime) {
                return 50; // neutral if no timestamp
            }
            
            $daysDiff = abs($lostTime - $foundTime) / (60 * 60 * 24);
            
            // Score decreases as time gap increases
            if ($daysDiff <= 1) return 100;
            if ($daysDiff <= 3) return 90;
            if ($daysDiff <= 7) return 75;
            if ($daysDiff <= 14) return 60;
            if ($daysDiff <= 30) return 40;
            return 20;
        }
        
        return 50; // neutral default
    }
    
    /**
     * Calculate overall match confidence between a lost item and found item
     * Returns confidence score (0-100) and reasoning
     */
    public function calculateMatch($lostItem, $foundItem) {
        // Title similarity
        $titleScore = $this->calculateTextSimilarity(
            $lostItem['title'],
            $foundItem['title']
        );
        
        // Description similarity
        $descScore = $this->calculateTextSimilarity(
            $lostItem['description'] ?? '',
            $foundItem['description'] ?? ''
        );
        
        // Location similarity
        $lostLoc = trim(($lostItem['building'] ?? '') . ' ' . ($lostItem['room'] ?? ''));
        $foundLoc = trim(($foundItem['building'] ?? '') . ' ' . ($foundItem['room'] ?? ''));
        $locScore = $this->calculateLocationSimilarity($lostLoc, $foundLoc);
        
        // Time proximity
        $timeScore = $this->calculateTimeProximity(
            $lostItem['item_id'],
            $foundItem['item_id']
        );
        
        // Weighted total
        $confidence = 
            ($titleScore * self::WEIGHT_TITLE) +
            ($descScore * self::WEIGHT_DESCRIPTION) +
            ($locScore * self::WEIGHT_LOCATION) +
            ($timeScore * self::WEIGHT_TIME);
        
        // Build reasoning text
        $reasons = [];
        if ($titleScore > 70) $reasons[] = "Very similar titles ({$titleScore}%)";
        elseif ($titleScore > 50) $reasons[] = "Similar titles ({$titleScore}%)";
        
        if ($descScore > 70) $reasons[] = "Very similar descriptions ({$descScore}%)";
        elseif ($descScore > 50) $reasons[] = "Similar descriptions ({$descScore}%)";
        
        if ($locScore > 80) $reasons[] = "Same location";
        elseif ($locScore > 60) $reasons[] = "Similar location ({$locScore}%)";
        
        if ($timeScore > 80) $reasons[] = "Posted around the same time";
        
        $reasoning = !empty($reasons) 
            ? implode('; ', $reasons) 
            : "Match based on overall similarity";
        
        return [
            'confidence' => round($confidence, 2),
            'reasoning' => $reasoning,
            'title_score' => round($titleScore, 2),
            'desc_score' => round($descScore, 2),
            'loc_score' => round($locScore, 2),
            'time_score' => round($timeScore, 2)
        ];
    }
    
    /**
     * Find all potential matches for lost items
     * Compares each lost item with all found items
     */
    public function findAllMatches($limit = null) {
        // Get all lost items
        $lostSql = "SELECT Item.item_id, Item.title, Item.description, Item.created_at,
                           Location.building, Location.room
                    FROM Item
                    LEFT JOIN At ON Item.item_id = At.item_id
                    LEFT JOIN Location ON At.location_id = Location.location_id
                    WHERE Item.item_type = 'lost' AND Item.status = 'available'";
        
        if ($limit) {
            $lostSql .= " LIMIT " . intval($limit);
        }
        
        $lostItems = $this->conn->query($lostSql);
        
        // Get all found items
        $foundSql = "SELECT Item.item_id, Item.title, Item.description, Item.created_at,
                            Location.building, Location.room
                     FROM Item
                     LEFT JOIN At ON Item.item_id = At.item_id
                     LEFT JOIN Location ON At.location_id = Location.location_id
                     WHERE Item.item_type = 'found' AND Item.status = 'available'";
        
        $foundItems = $this->conn->query($foundSql);
        $foundItemsArray = [];
        
        while ($found = $foundItems->fetch_assoc()) {
            $foundItemsArray[] = $found;
        }
        
        $matchesCreated = 0;
        $matchesSkipped = 0;
        
        // Compare each lost item with each found item
        while ($lost = $lostItems->fetch_assoc()) {
            foreach ($foundItemsArray as $found) {
                // Calculate match
                $match = $this->calculateMatch($lost, $found);
                
                if ($match['confidence'] >= self::MIN_CONFIDENCE) {
                    // Check if match already exists
                    $checkStmt = $this->conn->prepare(
                        "SELECT match_id FROM ItemMatch 
                         WHERE lost_item_id = ? AND found_item_id = ?"
                    );
                    $checkStmt->bind_param('ii', $lost['item_id'], $found['item_id']);
                    $checkStmt->execute();
                    $existing = $checkStmt->get_result();
                    
                    if ($existing->num_rows === 0) {
                        // Insert new match
                        $insertStmt = $this->conn->prepare(
                            "INSERT INTO ItemMatch 
                             (lost_item_id, found_item_id, confidence_score, match_reasoning) 
                             VALUES (?, ?, ?, ?)"
                        );
                        $insertStmt->bind_param(
                            'iids',
                            $lost['item_id'],
                            $found['item_id'],
                            $match['confidence'],
                            $match['reasoning']
                        );
                        
                        if ($insertStmt->execute()) {
                            $matchesCreated++;
                        }
                    } else {
                        $matchesSkipped++;
                    }
                }
            }
        }
        
        return [
            'matches_created' => $matchesCreated,
            'matches_skipped' => $matchesSkipped
        ];
    }
    
    /**
     * Get potential matches for a specific item
     */
    public function getMatchesForItem($itemId, $itemType) {
        if ($itemType === 'lost') {
            // Get matches where this is the lost item
            $sql = "SELECT 
                        im.match_id, im.confidence_score, im.match_reasoning, im.status,
                        i.item_id, i.title, i.description, i.photo,
                        u.user_id as poster_id, u.name as poster_name, u.email as poster_email,
                        l.building, l.room
                    FROM ItemMatch im
                    INNER JOIN Item i ON im.found_item_id = i.item_id
                    LEFT JOIN Posts p ON i.item_id = p.item_id
                    LEFT JOIN User u ON p.user_id = u.user_id
                    LEFT JOIN At a ON i.item_id = a.item_id
                    LEFT JOIN Location l ON a.location_id = l.location_id
                    WHERE im.lost_item_id = ? AND im.status = 'pending'
                    ORDER BY im.confidence_score DESC";
        } else {
            // Get matches where this is the found item
            $sql = "SELECT 
                        im.match_id, im.confidence_score, im.match_reasoning, im.status,
                        i.item_id, i.title, i.description, i.photo,
                        u.user_id as poster_id, u.name as poster_name, u.email as poster_email,
                        l.building, l.room
                    FROM ItemMatch im
                    INNER JOIN Item i ON im.lost_item_id = i.item_id
                    LEFT JOIN Posts p ON i.item_id = p.item_id
                    LEFT JOIN User u ON p.user_id = u.user_id
                    LEFT JOIN At a ON i.item_id = a.item_id
                    LEFT JOIN Location l ON a.location_id = l.location_id
                    WHERE im.found_item_id = ? AND im.status = 'pending'
                    ORDER BY im.confidence_score DESC";
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $itemId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $matches = [];
        while ($row = $result->fetch_assoc()) {
            $matches[] = $row;
        }
        
        return $matches;
    }
}
