<?php

$dbPath = getcwd() . '/database/database.sqlite';
if (!file_exists($dbPath)) {
    echo "no sqlite file at: $dbPath\n";
    exit(1);
}

try {
    $db = new PDO('sqlite:' . $dbPath);
    $stmt = $db->query("SELECT id, api_last_fetched_at, length(api_last_payload) as payload_len FROM users WHERE api_last_payload IS NOT NULL ORDER BY api_last_fetched_at DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo 'id=' . $row['id'] . ' fetched_at=' . $row['api_last_fetched_at'] . ' payload_len=' . $row['payload_len'] . "\n";
    } else {
        echo "no payload\n";
    }
} catch (Throwable $e) {
    echo 'error: ' . $e->getMessage() . "\n";
    exit(1);
}
