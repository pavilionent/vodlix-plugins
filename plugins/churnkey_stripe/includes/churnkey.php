<?php
/**
 * Churnkey Helper for Churnkey + Stripe Plugin
 * Provides Churnkey integration functions and Stripe mapping
 * 
 * @package ChurnkeyStripe
 */

if (!defined('BASEDIR')) {
    exit('No direct script access allowed');
}

/**
 * Generate HMAC-SHA256 auth hash for Churnkey
 * 
 * @param string $customerId Stripe customer ID
 * @return string Auth hash
 */
function churnkey_stripe_generate_auth_hash($customerId) {
    $apiKey = churnkey_stripe_get_api_key();
    
    if (empty($apiKey)) {
        churnkey_stripe_log('error', 'Cannot generate auth hash: API key not configured');
        return '';
    }
    
    return hash_hmac('sha256', $customerId, $apiKey);
}

/**
 * Get Stripe mapping for a user
 * 
 * @param int $userid User ID
 * @return array|null Mapping data or null if not found
 */
function churnkey_stripe_get_mapping($userid) {
    $db = churnkey_stripe_db();
    $table = $db->table('churnkey_stripe_map');
    
    $sql = "SELECT * FROM `{$table}` WHERE `userid` = " . intval($userid) . " LIMIT 1";
    return $db->getRow($sql);
}

/**
 * Get Stripe mapping by customer ID
 * 
 * @param string $customerId Stripe customer ID
 * @return array|null Mapping data or null if not found
 */
function churnkey_stripe_get_mapping_by_customer($customerId) {
    $db = churnkey_stripe_db();
    $table = $db->table('churnkey_stripe_map');
    
    $sql = "SELECT * FROM `{$table}` WHERE `stripe_customer_id` = " . $db->escape($customerId) . " LIMIT 1";
    return $db->getRow($sql);
}

/**
 * Save Stripe mapping for a user
 * 
 * @param int $userid User ID
 * @param string $customerId Stripe customer ID
 * @param string|null $subscriptionId Stripe subscription ID (optional)
 * @param string|null $email User email (optional)
 * @param string $status Subscription status (default: active)
 * @return bool Success
 */
function churnkey_stripe_save_mapping($userid, $customerId, $subscriptionId = null, $email = null, $status = 'active') {
    $db = churnkey_stripe_db();
    $table = $db->table('churnkey_stripe_map');
    
    // Check if mapping exists
    $existing = churnkey_stripe_get_mapping($userid);
    
    if ($existing) {
        // Update existing mapping
        $sets = array(
            "`stripe_customer_id` = " . $db->escape($customerId),
            "`updated_at` = NOW()"
        );
        
        if ($subscriptionId !== null) {
            $sets[] = "`stripe_subscription_id` = " . $db->escape($subscriptionId);
        }
        if ($email !== null) {
            $sets[] = "`stripe_email` = " . $db->escape($email);
        }
        if ($status) {
            $sets[] = "`status` = " . $db->escape($status);
        }
        
        $sql = "UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE `userid` = " . intval($userid);
        return $db->execute($sql) !== false;
    } else {
        // Insert new mapping
        $data = array(
            'userid' => $userid,
            'stripe_customer_id' => $customerId,
            'stripe_subscription_id' => $subscriptionId,
            'stripe_email' => $email,
            'status' => $status
        );
        
        return $db->insert('churnkey_stripe_map', $data) !== false;
    }
}

/**
 * Update subscription ID in mapping
 * 
 * @param int $userid User ID
 * @param string $subscriptionId Stripe subscription ID
 * @return bool Success
 */
function churnkey_stripe_update_subscription_id($userid, $subscriptionId) {
    $db = churnkey_stripe_db();
    $table = $db->table('churnkey_stripe_map');
    
    $sql = "UPDATE `{$table}` SET `stripe_subscription_id` = " . $db->escape($subscriptionId) . 
           ", `updated_at` = NOW() WHERE `userid` = " . intval($userid);
    
    return $db->execute($sql) !== false;
}

/**
 * Update subscription status in mapping
 * 
 * @param int $userid User ID
 * @param string $status Status (active, canceled, past_due, trialing, unpaid)
 * @return bool Success
 */
function churnkey_stripe_update_status($userid, $status) {
    $db = churnkey_stripe_db();
    $table = $db->table('churnkey_stripe_map');
    
    $sql = "UPDATE `{$table}` SET `status` = " . $db->escape($status) . 
           ", `updated_at` = NOW() WHERE `userid` = " . intval($userid);
    
    return $db->execute($sql) !== false;
}

/**
 * Delete mapping for a user
 * 
 * @param int $userid User ID
 * @return bool Success
 */
function churnkey_stripe_delete_mapping($userid) {
    $db = churnkey_stripe_db();
    return $db->delete('churnkey_stripe_map', "`userid` = " . intval($userid));
}

/**
 * Resolve Stripe customer ID for a user
 * First checks local mapping, then queries Stripe by email
 * 
 * @param int $userid User ID
 * @param string|null $email User email (will be looked up if not provided)
 * @return array Result with 'success', 'customer_id', 'subscription_id', 'source' keys
 */
function churnkey_stripe_resolve_customer($userid, $email = null) {
    // First, check local mapping
    $mapping = churnkey_stripe_get_mapping($userid);
    
    if ($mapping && !empty($mapping['stripe_customer_id'])) {
        churnkey_stripe_log('debug', 'Found customer in local mapping', array(
            'userid' => $userid,
            'customer_id' => $mapping['stripe_customer_id']
        ));
        
        return array(
            'success' => true,
            'customer_id' => $mapping['stripe_customer_id'],
            'subscription_id' => $mapping['stripe_subscription_id'],
            'source' => 'mapping'
        );
    }
    
    // Get email if not provided
    if ($email === null) {
        $email = churnkey_stripe_get_current_email();
        
        // If still no email, try to get from user ID
        if (!$email) {
            $email = churnkey_stripe_get_user_email_by_id($userid);
        }
    }
    
    if (!$email) {
        churnkey_stripe_log('warning', 'Cannot resolve customer: no email available', array(
            'userid' => $userid
        ));
        return array(
            'success' => false,
            'error' => 'User email not available'
        );
    }
    
    // Query Stripe by email
    $stripe = churnkey_stripe_client();
    
    if (!$stripe->isConfigured()) {
        churnkey_stripe_log('warning', 'Cannot resolve customer: Stripe not configured');
        return array(
            'success' => false,
            'error' => 'Stripe not configured for email lookup'
        );
    }
    
    $customer = $stripe->findCustomerByEmail($email);
    
    if (!$customer) {
        churnkey_stripe_log('debug', 'No Stripe customer found for email', array(
            'userid' => $userid,
            'email' => $email
        ));
        return array(
            'success' => false,
            'error' => 'No Stripe customer found for this email'
        );
    }
    
    $customerId = $customer['id'];
    
    // Try to get active subscription
    $subscription = $stripe->getActiveSubscription($customerId);
    $subscriptionId = $subscription ? $subscription['id'] : null;
    
    // Save to local mapping for future use
    churnkey_stripe_save_mapping($userid, $customerId, $subscriptionId, $email);
    
    churnkey_stripe_log('info', 'Resolved customer from Stripe API', array(
        'userid' => $userid,
        'customer_id' => $customerId,
        'subscription_id' => $subscriptionId
    ));
    
    return array(
        'success' => true,
        'customer_id' => $customerId,
        'subscription_id' => $subscriptionId,
        'source' => 'stripe_api'
    );
}

/**
 * Get Churnkey context for the current user
 * This is what gets passed to the Churnkey modal
 * 
 * @return array Context data or error
 */
function churnkey_stripe_get_context_for_current_user() {
    // Ensure user is logged in
    if (!churnkey_stripe_is_user_logged_in()) {
        return array(
            'success' => false,
            'error' => 'Not logged in'
        );
    }
    
    $userid = churnkey_stripe_get_current_userid();
    
    if (!$userid) {
        return array(
            'success' => false,
            'error' => 'Could not determine user ID'
        );
    }
    
    // Check if plugin is configured
    $config = churnkey_stripe_is_configured();
    if (!$config['configured']) {
        churnkey_stripe_log('error', 'Plugin not configured', array('missing' => $config['missing']));
        return array(
            'success' => false,
            'error' => 'Plugin not configured'
        );
    }
    
    // Resolve Stripe customer
    $resolution = churnkey_stripe_resolve_customer($userid);
    
    if (!$resolution['success']) {
        return array(
            'success' => false,
            'error' => $resolution['error']
        );
    }
    
    $customerId = $resolution['customer_id'];
    $subscriptionId = $resolution['subscription_id'];
    
    // If we have a customer but no subscription, try to fetch it
    if (empty($subscriptionId) && churnkey_stripe_is_stripe_configured()) {
        $stripe = churnkey_stripe_client();
        $subscription = $stripe->getActiveSubscription($customerId);
        
        if ($subscription) {
            $subscriptionId = $subscription['id'];
            // Update the mapping
            churnkey_stripe_update_subscription_id($userid, $subscriptionId);
        }
    }
    
    // Require subscription for cancellation
    if (empty($subscriptionId)) {
        return array(
            'success' => false,
            'error' => 'No active subscription found'
        );
    }
    
    // Generate auth hash
    $authHash = churnkey_stripe_generate_auth_hash($customerId);
    
    if (empty($authHash)) {
        return array(
            'success' => false,
            'error' => 'Could not generate auth hash'
        );
    }
    
    // Build context
    $context = array(
        'appId' => churnkey_stripe_get_app_id(),
        'mode' => churnkey_stripe_get_mode(),
        'record' => churnkey_stripe_get_record(),
        'provider' => 'stripe',
        'customerId' => $customerId,
        'subscriptionId' => $subscriptionId,
        'authHash' => $authHash
    );
    
    churnkey_stripe_log('info', 'Generated Churnkey context', array(
        'userid' => $userid,
        'customer_id' => $customerId,
        'subscription_id' => $subscriptionId
    ));
    
    return array(
        'success' => true,
        'context' => $context
    );
}

/**
 * Log a Churnkey event (from webhook or other source)
 * 
 * @param string $eventType Event type
 * @param array $data Event data
 * @return int|false Insert ID or false on failure
 */
function churnkey_stripe_log_event($eventType, $data) {
    $db = churnkey_stripe_db();
    
    $insertData = array(
        'event_type' => $eventType,
        'event_id' => isset($data['id']) ? $data['id'] : null,
        'customer_id' => isset($data['customer_id']) ? $data['customer_id'] : null,
        'subscription_id' => isset($data['subscription_id']) ? $data['subscription_id'] : null,
        'userid' => isset($data['userid']) ? $data['userid'] : null,
        'payload' => json_encode($data),
        'status' => 'received',
        'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null
    );
    
    $result = $db->insert('churnkey_events', $insertData);
    
    if ($result !== false) {
        return $db->lastInsertId();
    }
    
    return false;
}

/**
 * Update event status
 * 
 * @param int $eventId Event ID
 * @param string $status New status (processed, failed)
 * @param string|null $errorMessage Error message if failed
 * @return bool Success
 */
function churnkey_stripe_update_event_status($eventId, $status, $errorMessage = null) {
    $db = churnkey_stripe_db();
    $table = $db->table('churnkey_events');
    
    $sets = array(
        "`status` = " . $db->escape($status),
        "`processed_at` = NOW()"
    );
    
    if ($errorMessage !== null) {
        $sets[] = "`error_message` = " . $db->escape($errorMessage);
    }
    
    $sql = "UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE `id` = " . intval($eventId);
    
    return $db->execute($sql) !== false;
}

/**
 * Get recent events
 * 
 * @param int $limit Number of events to retrieve
 * @param string|null $eventType Filter by event type
 * @return array Events
 */
function churnkey_stripe_get_events($limit = 100, $eventType = null) {
    $db = churnkey_stripe_db();
    $table = $db->table('churnkey_events');
    
    $where = '1=1';
    if ($eventType !== null) {
        $where .= " AND `event_type` = " . $db->escape($eventType);
    }
    
    $sql = "SELECT * FROM `{$table}` WHERE {$where} ORDER BY `created_at` DESC LIMIT " . intval($limit);
    
    return $db->getRows($sql);
}
