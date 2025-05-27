<?php
/**
 * Debug User ID Issue
 * This script investigates why user_id is being stored as '0' instead of 'user_1'
 */

// Try to find WordPress installation
$wp_paths = [
    '/var/www/html/wp-load.php',
    '../../../wp-load.php',
    '../../../../wp-load.php',
    '/volume1/docker/primates/wp-load.php'
];

$wp_loaded = false;
foreach ($wp_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $wp_loaded = true;
        echo "WordPress loaded from: $path\n";
        break;
    }
}

if (!$wp_loaded) {
    echo "Could not find WordPress installation.\n";
    exit;
}

// Include the logging function
if (!function_exists('ps_log_error')) {
    function ps_log_error($message) {
        $log_file = WP_CONTENT_DIR . '/plugins/primates-shoppers/logs/error_log.txt';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
    }
}

global $wpdb;
$table_name = $wpdb->prefix . 'ps_cache';

echo "=== DEBUGGING USER_ID ISSUE ===\n\n";

// 1. Test the user identifier function
echo "1. Testing ps_get_user_identifier():\n";
if (function_exists('ps_get_user_identifier')) {
    $user_id = ps_get_user_identifier();
    echo "   Generated user_id: '$user_id'\n";
    echo "   Type: " . gettype($user_id) . "\n";
    echo "   Length: " . strlen($user_id) . "\n";
    echo "   Is string: " . (is_string($user_id) ? 'YES' : 'NO') . "\n";
} else {
    echo "   ERROR: ps_get_user_identifier function not found!\n";
}

// 2. Check current WordPress user
echo "\n2. WordPress User Status:\n";
if (is_user_logged_in()) {
    $wp_user_id = get_current_user_id();
    echo "   Logged in: YES\n";
    echo "   WordPress User ID: $wp_user_id\n";
    echo "   Type: " . gettype($wp_user_id) . "\n";
} else {
    echo "   Logged in: NO\n";
}

// 3. Test direct database insertion
echo "\n3. Testing Direct Database Insertion:\n";

$test_user_id = 'test_user_123';
$test_query_hash = 'test_hash_' . time();
$test_query_data = json_encode(['test' => 'data']);
$test_results = json_encode(['test' => 'results']);
$test_created_at = current_time('mysql');
$test_expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

echo "   About to insert test record with user_id: '$test_user_id'\n";

$insert_result = $wpdb->insert(
    $table_name,
    array(
        'query_hash' => $test_query_hash,
        'query_data' => $test_query_data,
        'results' => $test_results,
        'created_at' => $test_created_at,
        'expires_at' => $test_expires_at,
        'user_id' => $test_user_id
    )
);

if ($insert_result === false) {
    echo "   INSERT FAILED!\n";
    echo "   Error: " . $wpdb->last_error . "\n";
} else {
    echo "   INSERT SUCCESS!\n";
    echo "   Insert ID: " . $wpdb->insert_id . "\n";
    
    // Immediately query back what was inserted
    $inserted_record = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $wpdb->insert_id)
    );
    
    if ($inserted_record) {
        echo "   Retrieved record:\n";
        echo "     ID: " . $inserted_record->id . "\n";
        echo "     user_id: '" . $inserted_record->user_id . "'\n";
        echo "     user_id type: " . gettype($inserted_record->user_id) . "\n";
        echo "     query_hash: " . $inserted_record->query_hash . "\n";
        
        // Check if the user_id matches what we inserted
        if ($inserted_record->user_id === $test_user_id) {
            echo "   ✓ USER_ID MATCHES - No database issue\n";
        } else {
            echo "   ✗ USER_ID MISMATCH!\n";
            echo "     Expected: '$test_user_id'\n";
            echo "     Got: '" . $inserted_record->user_id . "'\n";
        }
    } else {
        echo "   ERROR: Could not retrieve inserted record!\n";
    }
}

// 4. Check table structure
echo "\n4. Table Structure:\n";
$columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
foreach ($columns as $column) {
    echo "   {$column->Field}: {$column->Type} (Null: {$column->Null}, Default: {$column->Default})\n";
}

// 5. Check existing problematic record
echo "\n5. Examining Existing Problematic Record (ID 7):\n";
$problem_record = $wpdb->get_row("SELECT * FROM $table_name WHERE id = 7");
if ($problem_record) {
    echo "   ID: " . $problem_record->id . "\n";
    echo "   user_id: '" . $problem_record->user_id . "'\n";
    echo "   user_id type: " . gettype($problem_record->user_id) . "\n";
    echo "   user_id length: " . strlen($problem_record->user_id) . "\n";
    echo "   query_hash: " . $problem_record->query_hash . "\n";
    echo "   created_at: " . $problem_record->created_at . "\n";
    
    // Check the query_data to see what user_id was supposed to be stored
    $query_data = json_decode($problem_record->query_data, true);
    if ($query_data && isset($query_data['user_id'])) {
        echo "   user_id in query_data: '" . $query_data['user_id'] . "'\n";
        if ($query_data['user_id'] !== $problem_record->user_id) {
            echo "   ✗ MISMATCH: query_data has different user_id than database field!\n";
        }
    }
} else {
    echo "   Record ID 7 not found\n";
}

// 6. Test with the exact same parameters as the problematic insertion
echo "\n6. Testing with Real Parameters from Log:\n";
$real_user_id = 'user_1';
$real_query_hash = '5da32353747cd49c65c455e8a963a616';
$real_query_data = json_encode([
    'query' => 'men shorts cotton',
    'country_code' => 'us',
    'exclude' => '',
    'sort_by' => 'price',
    'user_id' => $real_user_id
]);

echo "   Testing with user_id: '$real_user_id'\n";
echo "   Testing with query_hash: '$real_query_hash'\n";

$test_insert_2 = $wpdb->insert(
    $table_name,
    array(
        'query_hash' => $real_query_hash . '_test',
        'query_data' => $real_query_data,
        'results' => json_encode(['test' => 'real_params']),
        'created_at' => current_time('mysql'),
        'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
        'user_id' => $real_user_id
    )
);

if ($test_insert_2 === false) {
    echo "   INSERT FAILED!\n";
    echo "   Error: " . $wpdb->last_error . "\n";
} else {
    echo "   INSERT SUCCESS!\n";
    $new_id = $wpdb->insert_id;
    echo "   Insert ID: $new_id\n";
    
    $new_record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $new_id));
    if ($new_record) {
        echo "   Retrieved user_id: '" . $new_record->user_id . "'\n";
        if ($new_record->user_id === $real_user_id) {
            echo "   ✓ USER_ID MATCHES with real parameters\n";
        } else {
            echo "   ✗ USER_ID MISMATCH with real parameters!\n";
            echo "     Expected: '$real_user_id'\n";
            echo "     Got: '" . $new_record->user_id . "'\n";
        }
    }
}

// 7. Check for any database triggers or constraints
echo "\n7. Checking for Database Triggers:\n";
$triggers = $wpdb->get_results("SHOW TRIGGERS LIKE '$table_name'");
if (empty($triggers)) {
    echo "   No triggers found on table\n";
} else {
    foreach ($triggers as $trigger) {
        echo "   Trigger: {$trigger->Trigger} - Event: {$trigger->Event} - Timing: {$trigger->Timing}\n";
    }
}

echo "\n=== DEBUG COMPLETE ===\n";
?> 