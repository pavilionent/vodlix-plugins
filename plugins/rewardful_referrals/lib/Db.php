<?php
/**
 * Rewardful Referrals - Database Helper
 * 
 * Provides database access methods using ClipBucket's database layer
 */

namespace RewardfulReferrals;

if (!defined('BASEDIR')) {
    die('Direct access not allowed');
}

class Db {
    
    /**
     * @var \mysqli Database connection
     */
    private $db;
    
    /**
     * @var string Table prefix
     */
    private $prefix;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $db;
        $this->db = $db;
        $this->prefix = isset($db->db_prefix) ? $db->db_prefix : 'cb_';
    }
    
    /**
     * Get table name with prefix
     * 
     * @param string $table
     * @return string
     */
    public function table($table) {
        return $this->prefix . 'rewardful_' . $table;
    }
    
    // ========================================
    // SETTINGS
    // ========================================
    
    /**
     * Get a setting value
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getSetting($key, $default = null) {
        $table = $this->table('settings');
        $result = $this->db->select(
            "SELECT setting_value FROM `{$table}` WHERE setting_key = ?",
            array($key)
        );
        
        if (!empty($result) && isset($result[0]['setting_value'])) {
            return $result[0]['setting_value'];
        }
        
        return $default;
    }
    
    /**
     * Set a setting value
     * 
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function setSetting($key, $value) {
        $table = $this->table('settings');
        $sql = "INSERT INTO `{$table}` (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        return $this->db->execute($sql, array($key, $value));
    }
    
    /**
     * Get all settings
     * 
     * @return array
     */
    public function getAllSettings() {
        $table = $this->table('settings');
        $result = $this->db->select("SELECT * FROM `{$table}`");
        
        $settings = array();
        if (!empty($result)) {
            foreach ($result as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
        
        return $settings;
    }
    
    // ========================================
    // REFERRERS
    // ========================================
    
    /**
     * Get referrer by ID
     * 
     * @param int $id
     * @return array|null
     */
    public function getReferrerById($id) {
        $table = $this->table('referrers');
        $result = $this->db->select(
            "SELECT * FROM `{$table}` WHERE id = ?",
            array((int)$id)
        );
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get referrer by user ID
     * 
     * @param int $userid
     * @return array|null
     */
    public function getReferrerByUserId($userid) {
        $table = $this->table('referrers');
        $result = $this->db->select(
            "SELECT * FROM `{$table}` WHERE userid = ?",
            array((int)$userid)
        );
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get referrer by email
     * 
     * @param string $email
     * @return array|null
     */
    public function getReferrerByEmail($email) {
        $table = $this->table('referrers');
        $result = $this->db->select(
            "SELECT * FROM `{$table}` WHERE email = ?",
            array($email)
        );
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Create a new referrer
     * 
     * @param array $data
     * @return int|false Insert ID or false on failure
     */
    public function createReferrer($data) {
        $table = $this->table('referrers');
        
        $fields = array('email', 'userid', 'first_name', 'last_name', 'status', 'notes', 
                        'application_message', 'created_by_admin_userid');
        
        $insert_data = array();
        $placeholders = array();
        $values = array();
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $insert_data[] = "`{$field}`";
                $placeholders[] = "?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($insert_data)) {
            return false;
        }
        
        $sql = "INSERT INTO `{$table}` (" . implode(', ', $insert_data) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        $this->db->execute($sql, $values);
        return $this->db->insert_id();
    }
    
    /**
     * Update referrer
     * 
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function updateReferrer($id, $data) {
        $table = $this->table('referrers');
        
        $sets = array();
        $values = array();
        
        $allowed = array('email', 'userid', 'first_name', 'last_name', 'status', 'notes',
                         'approved_by_admin_userid', 'approved_at');
        
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "`{$field}` = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($sets)) {
            return false;
        }
        
        $values[] = (int)$id;
        
        $sql = "UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE id = ?";
        return $this->db->execute($sql, $values);
    }
    
    /**
     * Get referrers by status
     * 
     * @param string|array $status
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getReferrersByStatus($status, $limit = 50, $offset = 0) {
        $table = $this->table('referrers');
        
        if (is_array($status)) {
            $placeholders = implode(',', array_fill(0, count($status), '?'));
            $sql = "SELECT * FROM `{$table}` WHERE status IN ({$placeholders}) 
                    ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params = array_merge($status, array($limit, $offset));
        } else {
            $sql = "SELECT * FROM `{$table}` WHERE status = ? 
                    ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params = array($status, $limit, $offset);
        }
        
        return $this->db->select($sql, $params) ?: array();
    }
    
    /**
     * Get all referrers
     * 
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAllReferrers($limit = 50, $offset = 0) {
        $table = $this->table('referrers');
        $sql = "SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT ? OFFSET ?";
        return $this->db->select($sql, array($limit, $offset)) ?: array();
    }
    
    /**
     * Count referrers by status
     * 
     * @param string|null $status
     * @return int
     */
    public function countReferrers($status = null) {
        $table = $this->table('referrers');
        
        if ($status) {
            $sql = "SELECT COUNT(*) as cnt FROM `{$table}` WHERE status = ?";
            $result = $this->db->select($sql, array($status));
        } else {
            $sql = "SELECT COUNT(*) as cnt FROM `{$table}`";
            $result = $this->db->select($sql);
        }
        
        return !empty($result) ? (int)$result[0]['cnt'] : 0;
    }
    
    // ========================================
    // AFFILIATES
    // ========================================
    
    /**
     * Get affiliate by referrer ID
     * 
     * @param int $referrer_id
     * @return array|null
     */
    public function getAffiliateByReferrerId($referrer_id) {
        $table = $this->table('affiliates');
        $result = $this->db->select(
            "SELECT * FROM `{$table}` WHERE referrer_id = ?",
            array((int)$referrer_id)
        );
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get affiliate by token
     * 
     * @param string $token
     * @return array|null
     */
    public function getAffiliateByToken($token) {
        $table = $this->table('affiliates');
        $result = $this->db->select(
            "SELECT * FROM `{$table}` WHERE token = ?",
            array($token)
        );
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Create or update affiliate
     * 
     * @param int $referrer_id
     * @param array $data
     * @return int|false
     */
    public function upsertAffiliate($referrer_id, $data) {
        $table = $this->table('affiliates');
        
        $existing = $this->getAffiliateByReferrerId($referrer_id);
        
        if ($existing) {
            // Update
            $sets = array();
            $values = array();
            
            foreach ($data as $field => $value) {
                if ($field !== 'referrer_id' && $field !== 'id') {
                    $sets[] = "`{$field}` = ?";
                    $values[] = $value;
                }
            }
            
            if (!empty($sets)) {
                $sets[] = "`synced_at` = NOW()";
                $values[] = (int)$existing['id'];
                
                $sql = "UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE id = ?";
                $this->db->execute($sql, $values);
            }
            
            return (int)$existing['id'];
        } else {
            // Insert
            $data['referrer_id'] = $referrer_id;
            $data['synced_at'] = date('Y-m-d H:i:s');
            
            $fields = array();
            $placeholders = array();
            $values = array();
            
            foreach ($data as $field => $value) {
                $fields[] = "`{$field}`";
                $placeholders[] = "?";
                $values[] = $value;
            }
            
            $sql = "INSERT INTO `{$table}` (" . implode(', ', $fields) . ") 
                    VALUES (" . implode(', ', $placeholders) . ")";
            
            $this->db->execute($sql, $values);
            return $this->db->insert_id();
        }
    }
    
    // ========================================
    // CONVERSIONS
    // ========================================
    
    /**
     * Check if conversion already exists (idempotency)
     * 
     * @param string $conversion_key
     * @return bool
     */
    public function conversionExists($conversion_key) {
        $table = $this->table('conversions');
        $result = $this->db->select(
            "SELECT id FROM `{$table}` WHERE conversion_key = ?",
            array($conversion_key)
        );
        return !empty($result);
    }
    
    /**
     * Create conversion record
     * 
     * @param array $data
     * @return int|false
     */
    public function createConversion($data) {
        $table = $this->table('conversions');
        
        // Generate conversion key if not provided
        if (!isset($data['conversion_key'])) {
            $data['conversion_key'] = $this->generateConversionKey($data);
        }
        
        // Check idempotency
        if ($this->conversionExists($data['conversion_key'])) {
            return false;
        }
        
        $fields = array('userid', 'email', 'via_token', 'subscription_id', 'order_id', 
                        'amount', 'currency', 'status', 'conversion_key');
        
        $insert_data = array();
        $placeholders = array();
        $values = array();
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $insert_data[] = "`{$field}`";
                $placeholders[] = "?";
                $values[] = $data[$field];
            }
        }
        
        $sql = "INSERT INTO `{$table}` (" . implode(', ', $insert_data) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        $this->db->execute($sql, $values);
        return $this->db->insert_id();
    }
    
    /**
     * Generate unique conversion key
     * 
     * @param array $data
     * @return string
     */
    public function generateConversionKey($data) {
        $parts = array(
            isset($data['userid']) ? $data['userid'] : '',
            isset($data['email']) ? $data['email'] : '',
            isset($data['subscription_id']) ? $data['subscription_id'] : '',
            isset($data['order_id']) ? $data['order_id'] : '',
            date('Y-m-d') // Daily uniqueness if no order/subscription ID
        );
        
        return hash('sha256', implode('|', $parts));
    }
    
    /**
     * Update conversion status
     * 
     * @param int $id
     * @param string $status
     * @param string|null $error_message
     * @return bool
     */
    public function updateConversionStatus($id, $status, $error_message = null) {
        $table = $this->table('conversions');
        
        $sql = "UPDATE `{$table}` SET status = ?, error_message = ?";
        $params = array($status, $error_message);
        
        if ($status === 'sent') {
            $sql .= ", sent_at = NOW()";
        } elseif ($status === 'confirmed') {
            $sql .= ", confirmed_at = NOW()";
        }
        
        $sql .= " WHERE id = ?";
        $params[] = (int)$id;
        
        return $this->db->execute($sql, $params);
    }
    
    /**
     * Get recent conversions
     * 
     * @param int $limit
     * @return array
     */
    public function getRecentConversions($limit = 50) {
        $table = $this->table('conversions');
        $sql = "SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT ?";
        return $this->db->select($sql, array($limit)) ?: array();
    }
    
    // ========================================
    // EVENTS (Webhooks)
    // ========================================
    
    /**
     * Check if event already processed (idempotency)
     * 
     * @param string $event_id
     * @return bool
     */
    public function eventExists($event_id) {
        $table = $this->table('events');
        $result = $this->db->select(
            "SELECT id FROM `{$table}` WHERE event_id = ?",
            array($event_id)
        );
        return !empty($result);
    }
    
    /**
     * Create event record
     * 
     * @param array $data
     * @return int|false
     */
    public function createEvent($data) {
        $table = $this->table('events');
        
        // Check idempotency
        if (isset($data['event_id']) && $this->eventExists($data['event_id'])) {
            return false;
        }
        
        $sql = "INSERT INTO `{$table}` (event_id, event_type, payload_json, status) 
                VALUES (?, ?, ?, ?)";
        
        $this->db->execute($sql, array(
            $data['event_id'] ?? hash('sha256', $data['payload_json'] ?? ''),
            $data['event_type'] ?? 'unknown',
            $data['payload_json'] ?? '',
            $data['status'] ?? 'received'
        ));
        
        return $this->db->insert_id();
    }
    
    /**
     * Update event status
     * 
     * @param int $id
     * @param string $status
     * @param string|null $error_message
     * @return bool
     */
    public function updateEventStatus($id, $status, $error_message = null) {
        $table = $this->table('events');
        
        $sql = "UPDATE `{$table}` SET status = ?, error_message = ?";
        $params = array($status, $error_message);
        
        if ($status === 'processed') {
            $sql .= ", processed_at = NOW()";
        }
        
        $sql .= " WHERE id = ?";
        $params[] = (int)$id;
        
        return $this->db->execute($sql, $params);
    }
    
    /**
     * Get recent events
     * 
     * @param int $limit
     * @return array
     */
    public function getRecentEvents($limit = 50) {
        $table = $this->table('events');
        $sql = "SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT ?";
        return $this->db->select($sql, array($limit)) ?: array();
    }
    
    // ========================================
    // LOGS
    // ========================================
    
    /**
     * Add log entry
     * 
     * @param string $type
     * @param string $action
     * @param string $message
     * @param array|null $context
     * @param int|null $userid
     * @return int|false
     */
    public function log($type, $action, $message, $context = null, $userid = null) {
        $table = $this->table('logs');
        
        $sql = "INSERT INTO `{$table}` (log_type, action, message, context_json, userid, ip_address) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $this->db->execute($sql, array(
            $type,
            $action,
            $message,
            $context ? json_encode($context) : null,
            $userid,
            $_SERVER['REMOTE_ADDR'] ?? null
        ));
        
        return $this->db->insert_id();
    }
    
    /**
     * Get logs
     * 
     * @param string|null $type
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getLogs($type = null, $limit = 100, $offset = 0) {
        $table = $this->table('logs');
        
        if ($type) {
            $sql = "SELECT * FROM `{$table}` WHERE log_type = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params = array($type, $limit, $offset);
        } else {
            $sql = "SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params = array($limit, $offset);
        }
        
        return $this->db->select($sql, $params) ?: array();
    }
    
    /**
     * Clear old logs
     * 
     * @param int $days_old
     * @return bool
     */
    public function clearOldLogs($days_old = 90) {
        $table = $this->table('logs');
        $sql = "DELETE FROM `{$table}` WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        return $this->db->execute($sql, array($days_old));
    }
}
