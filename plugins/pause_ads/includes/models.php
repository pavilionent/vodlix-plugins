<?php
/**
 * Pause Ads v2.0 - Data Models
 *
 * CRUD operations for companies, campaigns, creatives,
 * packages, purchases, and targeting rules.
 *
 * @package PauseAds
 */

if (!defined('STARTER')) { die('No direct access allowed.'); }

// =========================================================================
// Companies
// =========================================================================

function pa_company_create($name, $billing_email, $owner_user_id)
{
    $t = pause_ads_table('pause_ads_companies');
    $id = pause_ads_db_execute(
        "INSERT INTO `{$t}` (`name`,`billing_email`) VALUES (?,?)",
        'ss', [$name, $billing_email]
    );
    if ($id) {
        $cu = pause_ads_table('pause_ads_company_users');
        pause_ads_db_execute("INSERT INTO `{$cu}` (`company_id`,`user_id`,`role`) VALUES (?,?,'owner')", 'ii', [$id, $owner_user_id]);
    }
    return $id;
}

function pa_company_get($id)
{
    $t = pause_ads_table('pause_ads_companies');
    return pause_ads_db_select_one("SELECT * FROM `{$t}` WHERE `id`=?", 'i', [$id]);
}

function pa_company_list($status = '')
{
    $t = pause_ads_table('pause_ads_companies');
    if ($status) return pause_ads_db_select("SELECT * FROM `{$t}` WHERE `status`=? ORDER BY `name`",'s',[$status]);
    return pause_ads_db_select("SELECT * FROM `{$t}` ORDER BY `name`");
}

function pa_company_update($id, $data)
{
    $t = pause_ads_table('pause_ads_companies');
    $sets = []; $params = []; $types = '';
    foreach (['name'=>'s','billing_email'=>'s','status'=>'s'] as $f=>$tp) {
        if (array_key_exists($f,$data)) { $sets[]="`{$f}`=?"; $params[]=$data[$f]; $types.=$tp; }
    }
    if (empty($sets)) return false;
    $params[] = (int)$id; $types .= 'i';
    return pause_ads_db_execute("UPDATE `{$t}` SET ".implode(',',$sets)." WHERE `id`=?", $types, $params) !== false;
}

function pa_company_add_user($company_id, $user_id, $role = 'admin')
{
    $t = pause_ads_table('pause_ads_company_users');
    return pause_ads_db_execute(
        "INSERT INTO `{$t}` (`company_id`,`user_id`,`role`) VALUES (?,?,?) ON DUPLICATE KEY UPDATE `role`=VALUES(`role`)",
        'iis', [$company_id, $user_id, $role]
    );
}

function pa_company_remove_user($company_id, $user_id)
{
    $t = pause_ads_table('pause_ads_company_users');
    return pause_ads_db_execute("DELETE FROM `{$t}` WHERE `company_id`=? AND `user_id`=?",'ii',[$company_id,$user_id]);
}

function pa_company_get_users($company_id)
{
    $t = pause_ads_table('pause_ads_company_users');
    return pause_ads_db_select("SELECT * FROM `{$t}` WHERE `company_id`=? ORDER BY `role`,`created_at`",'i',[$company_id]);
}

// =========================================================================
// Packages
// =========================================================================

function pa_package_list($status = '')
{
    $t = pause_ads_table('pause_ads_packages');
    if ($status) return pause_ads_db_select("SELECT * FROM `{$t}` WHERE `status`=? ORDER BY `sort_order`",'s',[$status]);
    return pause_ads_db_select("SELECT * FROM `{$t}` ORDER BY `sort_order`");
}

function pa_package_get($id)
{
    $t = pause_ads_table('pause_ads_packages');
    return pause_ads_db_select_one("SELECT * FROM `{$t}` WHERE `id`=?",'i',[$id]);
}

function pa_package_create($data)
{
    $t = pause_ads_table('pause_ads_packages');
    return pause_ads_db_execute(
        "INSERT INTO `{$t}` (`name`,`price_amount`,`currency`,`included_impressions`,`max_flight_days`,`status`,`sort_order`) VALUES (?,?,?,?,?,?,?)",
        'sdsiisi',
        [$data['name'], (float)$data['price_amount'], $data['currency'] ?? 'USD', (int)$data['included_impressions'],
         !empty($data['max_flight_days']) ? (int)$data['max_flight_days'] : 0, $data['status'] ?? 'active', (int)($data['sort_order'] ?? 0)]
    );
}

function pa_package_update($id, $data)
{
    $t = pause_ads_table('pause_ads_packages');
    $sets = []; $params = []; $types = '';
    $allowed = ['name'=>'s','price_amount'=>'d','currency'=>'s','included_impressions'=>'i','max_flight_days'=>'i','status'=>'s','sort_order'=>'i'];
    foreach ($allowed as $f=>$tp) {
        if (array_key_exists($f,$data)) { $sets[]="`{$f}`=?"; $params[]=$data[$f]; $types.=$tp; }
    }
    if (empty($sets)) return false;
    $params[]=(int)$id; $types.='i';
    return pause_ads_db_execute("UPDATE `{$t}` SET ".implode(',',$sets)." WHERE `id`=?",$types,$params) !== false;
}

// =========================================================================
// Campaigns
// =========================================================================

function pa_campaign_create($data)
{
    $t = pause_ads_table('pause_ads_campaigns');
    return pause_ads_db_execute(
        "INSERT INTO `{$t}` (`company_id`,`name`,`status`,`flight_start_at`,`flight_end_at`,`daily_cap`,`hourly_cap`,`freq_cap_per_user_per_day`,`priority`,`created_by_user_id`)
         VALUES (?,?,?,?,?,?,?,?,?,?)",
        'issssiiiii',
        [
            (int)$data['company_id'], $data['name'], $data['status'] ?? PA_STATUS_DRAFT,
            !empty($data['flight_start_at']) ? $data['flight_start_at'] : null,
            !empty($data['flight_end_at'])   ? $data['flight_end_at']   : null,
            !empty($data['daily_cap']) ? (int)$data['daily_cap'] : null,
            !empty($data['hourly_cap']) ? (int)$data['hourly_cap'] : null,
            !empty($data['freq_cap_per_user_per_day']) ? (int)$data['freq_cap_per_user_per_day'] : null,
            (int)($data['priority'] ?? 0),
            (int)($data['created_by_user_id'] ?? 0),
        ]
    );
}

function pa_campaign_get($id)
{
    $t = pause_ads_table('pause_ads_campaigns');
    $camp = pause_ads_db_select_one("SELECT * FROM `{$t}` WHERE `id`=?",'i',[$id]);
    if ($camp) {
        $camp['targeting'] = pa_targeting_get($id);
        $camp['creatives'] = pa_creative_list($id);
    }
    return $camp;
}

function pa_campaign_list($company_id = null, $status = '')
{
    $t = pause_ads_table('pause_ads_campaigns');
    $imp_t = pause_ads_table('pause_ads_impressions');
    $pur_t = pause_ads_table('pause_ads_purchases');

    $where = '1=1'; $types = ''; $params = [];
    if ($company_id) { $where .= " AND c.company_id=?"; $types .= 'i'; $params[] = $company_id; }
    if ($status) { $where .= " AND c.status=?"; $types .= 's'; $params[] = $status; }

    return pause_ads_db_select(
        "SELECT c.*,
                COALESCE(imp.cnt,0) as total_impressions,
                COALESCE(pur.remaining,0) as remaining_impressions,
                COALESCE(pur.granted,0) as granted_impressions
         FROM `{$t}` c
         LEFT JOIN (SELECT campaign_id, COUNT(*) as cnt FROM `{$imp_t}` GROUP BY campaign_id) imp ON imp.campaign_id=c.id
         LEFT JOIN (SELECT campaign_id, SUM(impressions_remaining) as remaining, SUM(impressions_granted) as granted FROM `{$pur_t}` WHERE payment_status='paid' GROUP BY campaign_id) pur ON pur.campaign_id=c.id
         WHERE {$where}
         ORDER BY c.created_at DESC",
        $types, $params
    );
}

function pa_campaign_update($id, $data)
{
    $t = pause_ads_table('pause_ads_campaigns');
    $sets = []; $params = []; $types = '';
    $allowed = ['name'=>'s','status'=>'s','flight_start_at'=>'s','flight_end_at'=>'s',
                'daily_cap'=>'i','hourly_cap'=>'i','freq_cap_per_user_per_day'=>'i','priority'=>'i'];
    foreach ($allowed as $f=>$tp) {
        if (array_key_exists($f,$data)) {
            $val = $data[$f];
            if (in_array($f,['flight_start_at','flight_end_at']) && empty($val)) $val = null;
            if (in_array($f,['daily_cap','hourly_cap','freq_cap_per_user_per_day']) && empty($val)) $val = null;
            $sets[]="`{$f}`=?"; $params[]=$val; $types.=$tp;
        }
    }
    if (empty($sets)) return false;
    $params[]=(int)$id; $types.='i';
    return pause_ads_db_execute("UPDATE `{$t}` SET ".implode(',',$sets)." WHERE `id`=?",$types,$params) !== false;
}

/**
 * Auto-end campaigns whose flight has expired or impressions ran out.
 */
function pa_campaigns_auto_end()
{
    $t = pause_ads_table('pause_ads_campaigns');
    $p = pause_ads_table('pause_ads_purchases');

    // End campaigns past flight end
    pause_ads_db_execute(
        "UPDATE `{$t}` SET `status`='ended' WHERE `status`='active' AND `flight_end_at` IS NOT NULL AND `flight_end_at` < NOW()"
    );

    // End campaigns with no remaining impressions
    $zero_camps = pause_ads_db_select(
        "SELECT c.id FROM `{$t}` c
         WHERE c.status='active'
           AND (SELECT COALESCE(SUM(pr.impressions_remaining),0) FROM `{$p}` pr WHERE pr.campaign_id=c.id AND pr.payment_status='paid') <= 0"
    );
    foreach ($zero_camps as $zc) {
        pause_ads_db_execute("UPDATE `{$t}` SET `status`='ended' WHERE `id`=? AND `status`='active'",'i',[$zc['id']]);
    }
}

// =========================================================================
// Creatives
// =========================================================================

function pa_creative_create($data)
{
    $t = pause_ads_table('pause_ads_creatives');
    return pause_ads_db_execute(
        "INSERT INTO `{$t}` (`campaign_id`,`name`,`image_path`,`image_width`,`image_height`,`click_url`,`alt_text`,`status`,`weight`)
         VALUES (?,?,?,?,?,?,?,?,?)",
        'issiiissi',
        [(int)$data['campaign_id'], $data['name'], $data['image_path'] ?? '',
         (int)($data['image_width']??0), (int)($data['image_height']??0),
         $data['click_url']??'', $data['alt_text']??'', $data['status']??'active', (int)($data['weight']??1)]
    );
}

function pa_creative_get($id)
{
    $t = pause_ads_table('pause_ads_creatives');
    return pause_ads_db_select_one("SELECT * FROM `{$t}` WHERE `id`=?",'i',[$id]);
}

function pa_creative_list($campaign_id)
{
    $t = pause_ads_table('pause_ads_creatives');
    return pause_ads_db_select("SELECT * FROM `{$t}` WHERE `campaign_id`=? ORDER BY `created_at` DESC",'i',[$campaign_id]);
}

function pa_creative_update($id, $data)
{
    $t = pause_ads_table('pause_ads_creatives');
    $sets=[]; $params=[]; $types='';
    $allowed = ['name'=>'s','image_path'=>'s','image_width'=>'i','image_height'=>'i','click_url'=>'s','alt_text'=>'s','status'=>'s','weight'=>'i'];
    foreach ($allowed as $f=>$tp) {
        if (array_key_exists($f,$data)) { $sets[]="`{$f}`=?"; $params[]=$data[$f]; $types.=$tp; }
    }
    if (empty($sets)) return false;
    $params[]=(int)$id; $types.='i';
    return pause_ads_db_execute("UPDATE `{$t}` SET ".implode(',',$sets)." WHERE `id`=?",$types,$params) !== false;
}

function pa_creative_delete($id)
{
    $cr = pa_creative_get($id);
    if ($cr && !empty($cr['image_path'])) {
        $f = PAUSE_ADS_UPLOADS_DIR.'/'.$cr['image_path'];
        if (file_exists($f)) @unlink($f);
    }
    $t = pause_ads_table('pause_ads_creatives');
    return pause_ads_db_execute("DELETE FROM `{$t}` WHERE `id`=?",'i',[$id]) !== false;
}

// =========================================================================
// Targeting rules
// =========================================================================

function pa_targeting_get($campaign_id)
{
    $t = pause_ads_table('pause_ads_targeting_rules');
    return pause_ads_db_select("SELECT * FROM `{$t}` WHERE `campaign_id`=? ORDER BY `rule_type`",'i',[$campaign_id]);
}

function pa_targeting_save($campaign_id, $rules)
{
    $t = pause_ads_table('pause_ads_targeting_rules');
    pause_ads_db_execute("DELETE FROM `{$t}` WHERE `campaign_id`=?",'i',[$campaign_id]);
    foreach ($rules as $r) {
        if (empty($r['rule_type']) || !isset($r['rule_value']) || $r['rule_value']==='') continue;
        pause_ads_db_execute("INSERT INTO `{$t}` (`campaign_id`,`rule_type`,`rule_value`) VALUES (?,?,?)",
            'iss', [(int)$campaign_id, trim($r['rule_type']), trim($r['rule_value'])]);
    }
    return true;
}

// =========================================================================
// Purchases
// =========================================================================

function pa_purchase_create($data)
{
    $t = pause_ads_table('pause_ads_purchases');
    return pause_ads_db_execute(
        "INSERT INTO `{$t}` (`company_id`,`user_id`,`package_id`,`campaign_id`,`impressions_granted`,`impressions_remaining`,`amount_paid`,`currency`,`payment_status`,`payment_gateway`,`payment_ref`)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)",
        'iiiiiiidsss',
        [
            (int)$data['company_id'], (int)$data['user_id'],
            !empty($data['package_id']) ? (int)$data['package_id'] : null,
            !empty($data['campaign_id']) ? (int)$data['campaign_id'] : null,
            (int)$data['impressions_granted'], (int)$data['impressions_granted'],
            (float)$data['amount_paid'], $data['currency'] ?? 'USD',
            $data['payment_status'] ?? 'pending',
            $data['payment_gateway'] ?? '', $data['payment_ref'] ?? '',
        ]
    );
}

function pa_purchase_get($id)
{
    $t = pause_ads_table('pause_ads_purchases');
    return pause_ads_db_select_one("SELECT * FROM `{$t}` WHERE `id`=?",'i',[$id]);
}

function pa_purchase_list($company_id = null)
{
    $t = pause_ads_table('pause_ads_purchases');
    $pk = pause_ads_table('pause_ads_packages');
    $where = $company_id ? "WHERE p.company_id=?" : "";
    $types = $company_id ? 'i' : '';
    $params = $company_id ? [$company_id] : [];

    return pause_ads_db_select(
        "SELECT p.*, pk.name as package_name
         FROM `{$t}` p LEFT JOIN `{$pk}` pk ON pk.id=p.package_id
         {$where} ORDER BY p.created_at DESC",
        $types, $params
    );
}

function pa_purchase_mark_paid($purchase_id, $payment_ref = '', $gateway = '')
{
    $t = pause_ads_table('pause_ads_purchases');
    return pause_ads_db_execute(
        "UPDATE `{$t}` SET `payment_status`='paid', `payment_ref`=?, `payment_gateway`=? WHERE `id`=?",
        'ssi', [$payment_ref, $gateway, (int)$purchase_id]
    ) !== false;
}

// =========================================================================
// Invoices
// =========================================================================

function pa_invoice_create($data)
{
    $t = pause_ads_table('pause_ads_invoices');
    // Generate invoice number
    $prefix = pause_ads_get_setting('invoice_prefix', 'PA');
    $count = (int) pause_ads_db_scalar("SELECT COUNT(*)+1 FROM `{$t}`");
    $inv_num = $prefix . '-' . date('Y') . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);

    return pause_ads_db_execute(
        "INSERT INTO `{$t}` (`company_id`,`user_id`,`purchase_id`,`invoice_number`,`line_items_json`,`subtotal`,`tax_amount`,`total_amount`,`currency`,`status`)
         VALUES (?,?,?,?,?,?,?,?,?,?)",
        'iiissdddss',
        [
            (int)$data['company_id'], (int)$data['user_id'],
            !empty($data['purchase_id']) ? (int)$data['purchase_id'] : null,
            $inv_num, json_encode($data['line_items'] ?? []),
            (float)($data['subtotal']??0), (float)($data['tax_amount']??0),
            (float)($data['total_amount']??0), $data['currency']??'USD', 'issued',
        ]
    );
}

function pa_invoice_get($id)
{
    $t = pause_ads_table('pause_ads_invoices');
    $inv = pause_ads_db_select_one("SELECT * FROM `{$t}` WHERE `id`=?",'i',[$id]);
    if ($inv) $inv['line_items'] = json_decode($inv['line_items_json'], true) ?: [];
    return $inv;
}

function pa_invoice_list($company_id = null)
{
    $t = pause_ads_table('pause_ads_invoices');
    if ($company_id) {
        return pause_ads_db_select("SELECT * FROM `{$t}` WHERE `company_id`=? ORDER BY `issued_at` DESC",'i',[$company_id]);
    }
    return pause_ads_db_select("SELECT * FROM `{$t}` ORDER BY `issued_at` DESC");
}

function pa_invoice_get_by_purchase($purchase_id)
{
    $t = pause_ads_table('pause_ads_invoices');
    return pause_ads_db_select_one("SELECT * FROM `{$t}` WHERE `purchase_id`=?",'i',[$purchase_id]);
}
