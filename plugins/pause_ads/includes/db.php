<?php
/**
 * Pause Ads Plugin - Database Helper Functions
 *
 * Provides centralized database access with prepared statements.
 *
 * @package PauseAds
 */

if (!defined('STARTER')) {
    die('No direct access allowed.');
}

/**
 * Get the ClipBucket table prefix.
 *
 * @return string
 */
function pause_ads_tbl_prefix()
{
    global $db;
    static $prefix = null;

    if ($prefix === null) {
        if (isset($db->db_prefix)) {
            $prefix = $db->db_prefix;
        } else {
            $prefix = 'cb_';
        }
    }

    return $prefix;
}

/**
 * Get the full table name with prefix.
 *
 * @param string $table Short table name (e.g., 'pause_ads_ads')
 * @return string Full prefixed table name
 */
function pause_ads_table($table)
{
    return pause_ads_tbl_prefix() . $table;
}

/**
 * Get the MySQLi connection object.
 *
 * @return mysqli
 */
function pause_ads_db()
{
    global $db;
    return $db->mysqli;
}

/**
 * Execute a prepared SELECT and return all rows.
 *
 * @param string $sql SQL with ? placeholders
 * @param string $types Bind param types string (e.g., 'si')
 * @param array  $params Bind param values
 * @return array Array of associative arrays
 */
function pause_ads_db_select($sql, $types = '', $params = [])
{
    $mysqli = pause_ads_db();
    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        error_log('Pause Ads DB Error (prepare): ' . $mysqli->error . ' | SQL: ' . $sql);
        return [];
    }

    if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }

    $stmt->close();
    return $rows;
}

/**
 * Execute a prepared SELECT and return a single row.
 *
 * @param string $sql SQL with ? placeholders
 * @param string $types Bind param types string
 * @param array  $params Bind param values
 * @return array|null Associative array or null
 */
function pause_ads_db_select_one($sql, $types = '', $params = [])
{
    $rows = pause_ads_db_select($sql, $types, $params);
    return !empty($rows) ? $rows[0] : null;
}

/**
 * Execute a prepared INSERT/UPDATE/DELETE statement.
 *
 * @param string $sql SQL with ? placeholders
 * @param string $types Bind param types string
 * @param array  $params Bind param values
 * @return int|false Affected rows or insert ID, or false on error
 */
function pause_ads_db_execute($sql, $types = '', $params = [])
{
    $mysqli = pause_ads_db();
    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        error_log('Pause Ads DB Error (prepare): ' . $mysqli->error . ' | SQL: ' . $sql);
        return false;
    }

    if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $result = $stmt->execute();

    if (!$result) {
        error_log('Pause Ads DB Error (execute): ' . $stmt->error);
        $stmt->close();
        return false;
    }

    $insert_id = $stmt->insert_id;
    $affected = $stmt->affected_rows;
    $stmt->close();

    return $insert_id > 0 ? $insert_id : $affected;
}

/**
 * Get a single scalar value from a query.
 *
 * @param string $sql SQL with ? placeholders
 * @param string $types Bind param types
 * @param array  $params Bind param values
 * @return mixed|null
 */
function pause_ads_db_scalar($sql, $types = '', $params = [])
{
    $row = pause_ads_db_select_one($sql, $types, $params);
    if ($row) {
        return reset($row);
    }
    return null;
}

/**
 * Get a plugin setting value.
 *
 * @param string $key Setting key
 * @param mixed  $default Default value if not found
 * @return string
 */
function pause_ads_get_setting($key, $default = '')
{
    static $cache = [];

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $table = pause_ads_table('pause_ads_settings');
    $value = pause_ads_db_scalar(
        "SELECT `setting_value` FROM `{$table}` WHERE `setting_key` = ?",
        's',
        [$key]
    );

    if ($value !== null) {
        $cache[$key] = $value;
        return $value;
    }

    return $default;
}

/**
 * Set a plugin setting value.
 *
 * @param string $key Setting key
 * @param string $value Setting value
 * @return bool
 */
function pause_ads_set_setting($key, $value)
{
    $table = pause_ads_table('pause_ads_settings');
    $result = pause_ads_db_execute(
        "INSERT INTO `{$table}` (`setting_key`, `setting_value`) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)",
        'ss',
        [$key, $value]
    );

    // Clear static cache
    static $cache_ref = null;

    return $result !== false;
}

/**
 * Check if the plugin is enabled.
 *
 * @return bool
 */
function pause_ads_is_enabled()
{
    return pause_ads_get_setting('enabled', '1') === '1';
}
