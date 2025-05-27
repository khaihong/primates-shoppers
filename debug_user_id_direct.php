<?php
/**
 * Debug User ID Issue - Direct Database Access
 * This script connects directly to the database to investigate the user_id issue
 */

echo "=== DEBUGGING USER_ID ISSUE (Direct DB) ===\n\n";

// Database connection settings from compose.yaml
$host = 'localhost';
$port = 3338;
$dbname = 'wp';
$username = 'Khai';
$password = 'Newpass!239';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Connected to database successfully\n\n";
    
    $table_name = 'wp_ps_cache';
    
    // 1. Check table structure
    echo "1. Table Structure:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM $table_name");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "   {$column['Field']}: {$column['Type']} (Null: {$column['Null']}, Default: {$column['Default']})\n";
    }
    
    // 2. Examine the problematic record (ID 7)
    echo "\n2. Examining Problematic Record (ID 7):\n";
    $stmt = $pdo->prepare("SELECT * FROM $table_name WHERE id = 7");
    $stmt->execute();
    $problem_record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($problem_record) {
        echo "   ID: " . $problem_record['id'] . "\n";
        echo "   user_id: '" . $problem_record['user_id'] . "'\n";
        echo "   user_id type: " . gettype($problem_record['user_id']) . "\n";
        echo "   user_id length: " . strlen($problem_record['user_id']) . "\n";
        echo "   query_hash: " . $problem_record['query_hash'] . "\n";
        echo "   created_at: " . $problem_record['created_at'] . "\n";
        
        // Check the query_data to see what user_id was supposed to be stored
        $query_data = json_decode($problem_record['query_data'], true);
        if ($query_data && isset($query_data['user_id'])) {
            echo "   user_id in query_data: '" . $query_data['user_id'] . "'\n";
            if ($query_data['user_id'] !== $problem_record['user_id']) {
                echo "   ✗ MISMATCH: query_data has different user_id than database field!\n";
                echo "     Expected (from query_data): '" . $query_data['user_id'] . "'\n";
                echo "     Actual (from user_id field): '" . $problem_record['user_id'] . "'\n";
            } else {
                echo "   ✓ user_id matches between query_data and database field\n";
            }
        } else {
            echo "   No user_id found in query_data\n";
        }
        
        // Show the full query_data for analysis
        echo "   Full query_data: " . $problem_record['query_data'] . "\n";
    } else {
        echo "   Record ID 7 not found\n";
    }
    
    // 3. Test direct insertion with known values
    echo "\n3. Testing Direct Database Insertion:\n";
    
    $test_user_id = 'test_user_direct_123';
    $test_query_hash = 'test_hash_direct_' . time();
    $test_query_data = json_encode([
        'query' => 'test query',
        'country_code' => 'us',
        'exclude' => '',
        'sort_by' => 'price',
        'user_id' => $test_user_id
    ]);
    $test_results = json_encode(['test' => 'results']);
    $test_created_at = date('Y-m-d H:i:s');
    $test_expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    echo "   About to insert test record with user_id: '$test_user_id'\n";
    
    $insert_stmt = $pdo->prepare("
        INSERT INTO $table_name 
        (query_hash, query_data, results, created_at, expires_at, user_id) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $insert_result = $insert_stmt->execute([
        $test_query_hash,
        $test_query_data,
        $test_results,
        $test_created_at,
        $test_expires_at,
        $test_user_id
    ]);
    
    if ($insert_result) {
        $new_id = $pdo->lastInsertId();
        echo "   ✓ INSERT SUCCESS! Insert ID: $new_id\n";
        
        // Immediately query back what was inserted
        $check_stmt = $pdo->prepare("SELECT * FROM $table_name WHERE id = ?");
        $check_stmt->execute([$new_id]);
        $inserted_record = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($inserted_record) {
            echo "   Retrieved record:\n";
            echo "     ID: " . $inserted_record['id'] . "\n";
            echo "     user_id: '" . $inserted_record['user_id'] . "'\n";
            echo "     user_id type: " . gettype($inserted_record['user_id']) . "\n";
            echo "     query_hash: " . $inserted_record['query_hash'] . "\n";
            
            // Check if the user_id matches what we inserted
            if ($inserted_record['user_id'] === $test_user_id) {
                echo "   ✓ USER_ID MATCHES - Database insertion works correctly\n";
            } else {
                echo "   ✗ USER_ID MISMATCH!\n";
                echo "     Expected: '$test_user_id'\n";
                echo "     Got: '" . $inserted_record['user_id'] . "'\n";
            }
        } else {
            echo "   ERROR: Could not retrieve inserted record!\n";
        }
    } else {
        echo "   ✗ INSERT FAILED!\n";
        $errorInfo = $insert_stmt->errorInfo();
        echo "   Error: " . $errorInfo[2] . "\n";
    }
    
    // 4. Test with exact same parameters as the problematic case
    echo "\n4. Testing with Exact Parameters from Problematic Case:\n";
    
    $real_user_id = 'user_1';
    $real_query_hash = '5da32353747cd49c65c455e8a963a616_debug';
    $real_query_data = json_encode([
        'query' => 'men shorts cotton',
        'country_code' => 'us',
        'exclude' => '',
        'sort_by' => 'price',
        'user_id' => $real_user_id
    ]);
    
    echo "   Testing with user_id: '$real_user_id'\n";
    
    $real_insert_stmt = $pdo->prepare("
        INSERT INTO $table_name 
        (query_hash, query_data, results, created_at, expires_at, user_id) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $real_insert_result = $real_insert_stmt->execute([
        $real_query_hash,
        $real_query_data,
        json_encode(['test' => 'real_params_debug']),
        date('Y-m-d H:i:s'),
        date('Y-m-d H:i:s', strtotime('+24 hours')),
        $real_user_id
    ]);
    
    if ($real_insert_result) {
        $real_new_id = $pdo->lastInsertId();
        echo "   ✓ INSERT SUCCESS! Insert ID: $real_new_id\n";
        
        $real_check_stmt = $pdo->prepare("SELECT * FROM $table_name WHERE id = ?");
        $real_check_stmt->execute([$real_new_id]);
        $real_inserted_record = $real_check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($real_inserted_record) {
            echo "   Retrieved user_id: '" . $real_inserted_record['user_id'] . "'\n";
            if ($real_inserted_record['user_id'] === $real_user_id) {
                echo "   ✓ USER_ID MATCHES with real parameters\n";
            } else {
                echo "   ✗ USER_ID MISMATCH with real parameters!\n";
                echo "     Expected: '$real_user_id'\n";
                echo "     Got: '" . $real_inserted_record['user_id'] . "'\n";
            }
        }
    } else {
        echo "   ✗ INSERT FAILED!\n";
        $errorInfo = $real_insert_stmt->errorInfo();
        echo "   Error: " . $errorInfo[2] . "\n";
    }
    
    // 5. Check for any database triggers
    echo "\n5. Checking for Database Triggers:\n";
    $trigger_stmt = $pdo->query("SHOW TRIGGERS LIKE '$table_name'");
    $triggers = $trigger_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($triggers)) {
        echo "   No triggers found on table\n";
    } else {
        foreach ($triggers as $trigger) {
            echo "   Trigger: {$trigger['Trigger']} - Event: {$trigger['Event']} - Timing: {$trigger['Timing']}\n";
        }
    }
    
    // 6. Show all records to see the pattern
    echo "\n6. All Records in Cache Table:\n";
    $all_stmt = $pdo->query("SELECT id, user_id, query_hash, created_at FROM $table_name ORDER BY id DESC LIMIT 10");
    $all_records = $all_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    printf("   %-4s %-20s %-20s %-20s\n", "ID", "USER_ID", "QUERY_HASH", "CREATED_AT");
    echo "   " . str_repeat("-", 70) . "\n";
    
    foreach ($all_records as $record) {
        $user_id_display = $record['user_id'] === null ? 'NULL' : "'" . $record['user_id'] . "'";
        $hash_short = substr($record['query_hash'], 0, 16) . '...';
        printf("   %-4s %-20s %-20s %-20s\n", 
            $record['id'], 
            $user_id_display, 
            $hash_short, 
            $record['created_at']
        );
    }
    
} catch (PDOException $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
}

echo "\n=== DEBUG COMPLETE ===\n";
?> 