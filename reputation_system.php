<?php
/**
 * Reputation & Trust Score System
 * Manages user reputation points, badges, and trust scores
 */

require_once 'db_connect.php';

class ReputationSystem {
    private $conn;
    
    // Point values for different actions
    const POINTS_POST_ITEM = 5;
    const POINTS_SUBMIT_CLAIM = 2;
    const POINTS_CLAIM_APPROVED = 10;
    const POINTS_CLAIM_REJECTED = -5;
    const POINTS_ITEM_RETURNED = 20;
    const POINTS_MATCH_CONFIRMED = 15;
    const POINTS_ACCOUNT_VERIFIED = 25;
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }
    
    /**
     * Award points to a user for an action
     */
    public function awardPoints($user_id, $action_type, $points, $related_item_id = null, $related_claim_id = null) {
        $this->conn->begin_transaction();
        
        try {
            // Log the action
            $stmt = $this->conn->prepare(
                "INSERT INTO UserAction (user_id, action_type, points_awarded, related_item_id, related_claim_id)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('isiii', $user_id, $action_type, $points, $related_item_id, $related_claim_id);
            $stmt->execute();
            
            // Update user's reputation score
            $update = $this->conn->prepare(
                "UPDATE User SET reputation_score = reputation_score + ? WHERE user_id = ?"
            );
            $update->bind_param('ii', $points, $user_id);
            $update->execute();
            
            // Check if user earned any new badges
            $this->checkAndAwardBadges($user_id);
            
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            return false;
        }
    }
    
    /**
     * Get user's current reputation score
     */
    public function getUserReputation($user_id) {
        $stmt = $this->conn->prepare(
            "SELECT reputation_score, verified FROM User WHERE user_id = ?"
        );
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return [
                'score' => intval($row['reputation_score']),
                'verified' => boolval($row['verified'])
            ];
        }
        
        return ['score' => 0, 'verified' => false];
    }
    
    /**
     * Get user statistics
     */
    public function getUserStats($user_id) {
        // Items posted
        $items_posted = $this->conn->query(
            "SELECT COUNT(*) as count FROM Posts WHERE user_id = $user_id"
        )->fetch_assoc()['count'];
        
        // Claims submitted
        $claims_submitted = $this->conn->query(
            "SELECT COUNT(*) as count FROM Submits WHERE user_id = $user_id"
        )->fetch_assoc()['count'];
        
        // Claims approved
        $claims_approved = $this->conn->query(
            "SELECT COUNT(*) as count FROM Claim c
             INNER JOIN Submits s ON c.claim_id = s.claim_id
             WHERE s.user_id = $user_id AND c.status = 'approved'"
        )->fetch_assoc()['count'];
        
        // Items returned (where user is the poster)
        $items_returned = $this->conn->query(
            "SELECT COUNT(*) as count FROM Item i
             INNER JOIN Posts p ON i.item_id = p.item_id
             WHERE p.user_id = $user_id AND i.status = 'returned'"
        )->fetch_assoc()['count'];
        
        // Matches confirmed
        $matches_confirmed = $this->conn->query(
            "SELECT COUNT(*) as count FROM ItemMatch im
             INNER JOIN Item i ON (im.lost_item_id = i.item_id OR im.found_item_id = i.item_id)
             INNER JOIN Posts p ON i.item_id = p.item_id
             WHERE p.user_id = $user_id AND im.status = 'confirmed'"
        )->fetch_assoc()['count'];
        
        // Success rate (approved claims / submitted claims)
        $success_rate = $claims_submitted > 0 
            ? round(($claims_approved / $claims_submitted) * 100, 1) 
            : 0;
        
        return [
            'items_posted' => $items_posted,
            'claims_submitted' => $claims_submitted,
            'claims_approved' => $claims_approved,
            'items_returned' => $items_returned,
            'matches_confirmed' => $matches_confirmed,
            'success_rate' => $success_rate
        ];
    }
    
    /**
     * Get user's badges
     */
    public function getUserBadges($user_id) {
        $stmt = $this->conn->prepare(
            "SELECT b.badge_id, b.badge_name, b.badge_description, b.badge_icon, 
                    b.badge_level, ub.earned_at
             FROM UserBadge ub
             INNER JOIN Badge b ON ub.badge_id = b.badge_id
             WHERE ub.user_id = ?
             ORDER BY ub.earned_at DESC"
        );
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $badges = [];
        while ($row = $result->fetch_assoc()) {
            $badges[] = $row;
        }
        
        return $badges;
    }
    
    /**
     * Check if user qualifies for any badges and award them
     */
    public function checkAndAwardBadges($user_id) {
        $reputation = $this->getUserReputation($user_id);
        $stats = $this->getUserStats($user_id);
        
        // Get member duration in days
        $member_stmt = $this->conn->prepare(
            "SELECT DATEDIFF(NOW(), member_since) as days FROM User WHERE user_id = ?"
        );
        $member_stmt->bind_param('i', $user_id);
        $member_stmt->execute();
        $member_result = $member_stmt->get_result();
        $member_days = $member_result->fetch_assoc()['days'] ?? 0;
        
        // Get all badges
        $all_badges = $this->conn->query("SELECT * FROM Badge");
        
        while ($badge = $all_badges->fetch_assoc()) {
            // Check if user already has this badge
            $check = $this->conn->prepare(
                "SELECT 1 FROM UserBadge WHERE user_id = ? AND badge_id = ?"
            );
            $check->bind_param('ii', $user_id, $badge['badge_id']);
            $check->execute();
            $has_badge = $check->get_result()->num_rows > 0;
            
            if ($has_badge) continue;
            
            // Check if user qualifies
            $qualifies = false;
            
            switch ($badge['requirement_type']) {
                case 'reputation_score':
                    $qualifies = $reputation['score'] >= $badge['requirement_value'];
                    break;
                case 'items_posted':
                    $qualifies = $stats['items_posted'] >= $badge['requirement_value'];
                    break;
                case 'items_returned':
                    $qualifies = $stats['items_returned'] >= $badge['requirement_value'];
                    break;
                case 'claims_approved':
                    $qualifies = $stats['claims_approved'] >= $badge['requirement_value'];
                    break;
                case 'verified_account':
                    $qualifies = $reputation['verified'];
                    break;
                case 'time_member':
                    $qualifies = $member_days >= $badge['requirement_value'];
                    break;
            }
            
            // Award badge if qualified
            if ($qualifies) {
                $award = $this->conn->prepare(
                    "INSERT IGNORE INTO UserBadge (user_id, badge_id) VALUES (?, ?)"
                );
                $award->bind_param('ii', $user_id, $badge['badge_id']);
                $award->execute();
            }
        }
    }
    
    /**
     * Get trust level based on reputation score
     */
    public function getTrustLevel($score) {
        if ($score >= 500) return ['level' => 'Legendary', 'color' => '#9b59b6', 'icon' => '🏆'];
        if ($score >= 250) return ['level' => 'Elite', 'color' => '#f39c12', 'icon' => '👑'];
        if ($score >= 100) return ['level' => 'Highly Trusted', 'color' => '#3498db', 'icon' => '💎'];
        if ($score >= 50) return ['level' => 'Trusted', 'color' => '#27ae60', 'icon' => '✅'];
        if ($score >= 20) return ['level' => 'Active', 'color' => '#95a5a6', 'icon' => '⭐'];
        return ['level' => 'Newcomer', 'color' => '#bdc3c7', 'icon' => '🆕'];
    }
    
    /**
     * Get reputation leaderboard
     */
    public function getLeaderboard($limit = 10) {
        $stmt = $this->conn->prepare(
            "SELECT u.user_id, u.name, u.reputation_score, u.verified,
                    (SELECT COUNT(*) FROM Posts WHERE user_id = u.user_id) as items_posted,
                    (SELECT COUNT(*) FROM Item i 
                     INNER JOIN Posts p ON i.item_id = p.item_id 
                     WHERE p.user_id = u.user_id AND i.status = 'returned') as items_returned
             FROM User u
             ORDER BY u.reputation_score DESC
             LIMIT ?"
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $leaderboard = [];
        while ($row = $result->fetch_assoc()) {
            $leaderboard[] = $row;
        }
        
        return $leaderboard;
    }
}
