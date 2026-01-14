<?php
/**
 * Database Helper for Churnkey + Stripe Plugin
 * Provides database abstraction using ClipBucket's DB layer
 * 
 * @package ChurnkeyStripe
 */

if (!defined('BASEDIR')) {
    exit('No direct script access allowed');
}

/**
 * Class ChurnkeyStripeDb
 * Database helper that uses ClipBucket's database layer
 */
class ChurnkeyStripeDb {
    
    /**
     * @var string Table prefix
     */
    private $prefix;
    
    /**
     * @var mysqli|PDO|object Database connection
     */
    private $db;
    
    /**
     * @var self Singleton instance
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        global $db, $myquery, $Cbucket;
        
        // Determine table prefix
        $this->prefix = $this->detectTablePrefix();
        
        // Get database connection
        $this->db = $this->detectDbConnection();
    }
    
    /**
     * Detect and return the table prefix
     */
    private function detectTablePrefix() {
        global $Cbucket;
        
        if (isset($Cbucket->table_prefix)) {
            return $Cbucket->table_prefix;
        }
        if (defined('TABLE_PREFIX')) {
            return TABLE_PREFIX;
        }
        if (defined('CB_PREFIX')) {
            return CB_PREFIX;
        }
        return 'cb_';
    }
    
    /**
     * Detect and return the database connection
     */
    private function detectDbConnection() {
        global $db, $myquery;
        
        if (isset($db) && is_object($db)) {
            return $db;
        }
        if (isset($myquery) && is_object($myquery)) {
            return $myquery;
        }
        if (isset($GLOBALS['mysqli']) && $GLOBALS['mysqli'] instanceof mysqli) {
            return $GLOBALS['mysqli'];
        }
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            return $GLOBALS['pdo'];
        }
        return null;
    }
    
    /**
     * Get table name with prefix
     */
    public function table($name) {
        return $this->prefix . $name;
    }
    
    /**
     * Get the prefix
     */
    public function getPrefix() {
        return $this->prefix;
    }
    
    /**
     * Execute a query
     */
    public function query($sql) {
        if (!$this->db) {
            return false;
        }
        
        try {
            // ClipBucket DB object
            if (method_exists($this->db, 'query')) {
                return $this->db->query($sql);
            }
            // ClipBucket myquery object
            if (method_exists($this->db, 'Execute')) {
                return $this->db->Execute($sql);
            }
            // mysqli
            if ($this->db instanceof mysqli) {
                return $this->db->query($sql);
            }
            // PDO
            if ($this->db instanceof PDO) {
                return $this->db->query($sql);
            }
        } catch (Exception $e) {
            $this->logError('Query failed: ' . $e->getMessage(), array('sql' => $sql));
            return false;
        }
        
        return false;
    }
    
    /**
     * Execute a prepared statement (INSERT/UPDATE/DELETE)
     */
    public function execute($sql) {
        if (!$this->db) {
            return false;
        }
        
        try {
            // ClipBucket DB object
            if (method_exists($this->db, 'execute')) {
                return $this->db->execute($sql);
            }
            // ClipBucket myquery object
            if (method_exists($this->db, 'Execute')) {
                return $this->db->Execute($sql);
            }
            // mysqli
            if ($this->db instanceof mysqli) {
                return $this->db->query($sql);
            }
            // PDO
            if ($this->db instanceof PDO) {
                return $this->db->exec($sql);
            }
        } catch (Exception $e) {
            $this->logError('Execute failed: ' . $e->getMessage(), array('sql' => $sql));
            return false;
        }
        
        return false;
    }
    
    /**
     * Fetch a single row
     */
    public function getRow($sql) {
        $result = $this->query($sql);
        
        if (!$result) {
            return null;
        }
        
        // ClipBucket style
        if (method_exists($this->db, 'getRow')) {
            return $this->db->getRow($sql);
        }
        
        // mysqli result
        if ($result instanceof mysqli_result) {
            $row = $result->fetch_assoc();
            $result->free();
            return $row;
        }
        
        // PDO result
        if ($result instanceof PDOStatement) {
            return $result->fetch(PDO::FETCH_ASSOC);
        }
        
        // Array result (some ClipBucket versions)
        if (is_array($result) && !empty($result)) {
            return $result[0];
        }
        
        return null;
    }
    
    /**
     * Fetch all rows
     */
    public function getRows($sql) {
        $result = $this->query($sql);
        
        if (!$result) {
            return array();
        }
        
        // ClipBucket style
        if (method_exists($this->db, 'getRows')) {
            return $this->db->getRows($sql);
        }
        
        // mysqli result
        if ($result instanceof mysqli_result) {
            $rows = array();
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $result->free();
            return $rows;
        }
        
        // PDO result
        if ($result instanceof PDOStatement) {
            return $result->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Array result
        if (is_array($result)) {
            return $result;
        }
        
        return array();
    }
    
    /**
     * Get last insert ID
     */
    public function lastInsertId() {
        if (!$this->db) {
            return 0;
        }
        
        // ClipBucket DB object
        if (method_exists($this->db, 'insert_id')) {
            return $this->db->insert_id();
        }
        if (method_exists($this->db, 'lastInsertId')) {
            return $this->db->lastInsertId();
        }
        
        // mysqli
        if ($this->db instanceof mysqli) {
            return $this->db->insert_id;
        }
        
        // PDO
        if ($this->db instanceof PDO) {
            return $this->db->lastInsertId();
        }
        
        return 0;
    }
    
    /**
     * Escape a string for SQL
     */
    public function escape($string) {
        if ($string === null) {
            return 'NULL';
        }
        
        if (!$this->db) {
            return "'" . addslashes($string) . "'";
        }
        
        // ClipBucket DB object
        if (method_exists($this->db, 'clean') && method_exists($this->db, 'escape')) {
            return "'" . $this->db->escape($string) . "'";
        }
        
        // mysqli
        if ($this->db instanceof mysqli) {
            return "'" . $this->db->real_escape_string($string) . "'";
        }
        
        // PDO
        if ($this->db instanceof PDO) {
            return $this->db->quote($string);
        }
        
        return "'" . addslashes($string) . "'";
    }
    
    /**
     * Escape without quotes (for numbers, etc.)
     */
    public function escapeRaw($string) {
        if ($string === null) {
            return 'NULL';
        }
        
        if (!$this->db) {
            return addslashes($string);
        }
        
        // ClipBucket DB object
        if (method_exists($this->db, 'escape')) {
            return $this->db->escape($string);
        }
        
        // mysqli
        if ($this->db instanceof mysqli) {
            return $this->db->real_escape_string($string);
        }
        
        return addslashes($string);
    }
    
    /**
     * Insert a row
     */
    public function insert($table, $data) {
        $table = $this->table($table);
        
        $columns = array();
        $values = array();
        
        foreach ($data as $column => $value) {
            $columns[] = '`' . $this->escapeRaw($column) . '`';
            $values[] = $this->escape($value);
        }
        
        $sql = "INSERT INTO `{$table}` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
        
        return $this->execute($sql);
    }
    
    /**
     * Update rows
     */
    public function update($table, $data, $where) {
        $table = $this->table($table);
        
        $sets = array();
        foreach ($data as $column => $value) {
            $sets[] = '`' . $this->escapeRaw($column) . '` = ' . $this->escape($value);
        }
        
        $sql = "UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE " . $where;
        
        return $this->execute($sql);
    }
    
    /**
     * Delete rows
     */
    public function delete($table, $where) {
        $table = $this->table($table);
        
        $sql = "DELETE FROM `{$table}` WHERE " . $where;
        
        return $this->execute($sql);
    }
    
    /**
     * Log an error
     */
    private function logError($message, $context = array()) {
        // Use the logging function if available
        if (function_exists('churnkey_stripe_log')) {
            churnkey_stripe_log('error', $message, $context);
        }
    }
}

/**
 * Get database helper instance (convenience function)
 */
function churnkey_stripe_db() {
    return ChurnkeyStripeDb::getInstance();
}
