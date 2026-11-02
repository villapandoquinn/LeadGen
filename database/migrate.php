<?php
require_once __DIR__ . '/../config/database.php';

echo "Running migrations...\n";

try {
    // 1. Modify email column in leads to allow NULL if it doesn't already
    $pdo->exec("ALTER TABLE leads MODIFY COLUMN email VARCHAR(255) NULL DEFAULT NULL");
    echo "[OK] leads.email column is nullable\n";
} catch (Exception $e) {
    echo "[INFO] leads.email modification: " . $e->getMessage() . "\n";
}

// 2. Add google_place_id column to leads if not exists
try {
    $check = $pdo->query("SHOW COLUMNS FROM leads LIKE 'google_place_id'")->fetch();
    if (!$check) {
        $pdo->exec("ALTER TABLE leads ADD COLUMN google_place_id VARCHAR(255) NULL AFTER campaign_id, ADD INDEX idx_leads_place_id (google_place_id)");
        echo "[OK] Added google_place_id to leads table\n";
    } else {
        echo "[OK] google_place_id already exists in leads table\n";
    }
} catch (Exception $e) {
    echo "[ERROR] google_place_id: " . $e->getMessage() . "\n";
}

// 3. Add rating, reviews_count, address to leads if not exists
try {
    $check = $pdo->query("SHOW COLUMNS FROM leads LIKE 'rating'")->fetch();
    if (!$check) {
        $pdo->exec("ALTER TABLE leads ADD COLUMN rating DECIMAL(3,1) NULL AFTER score, ADD COLUMN reviews_count INT UNSIGNED NULL DEFAULT 0 AFTER rating");
        echo "[OK] Added rating and reviews_count to leads table\n";
    } else {
        echo "[OK] rating already exists in leads table\n";
    }
} catch (Exception $e) {
    echo "[ERROR] rating: " . $e->getMessage() . "\n";
}

try {
    $check = $pdo->query("SHOW COLUMNS FROM leads LIKE 'address'")->fetch();
    if (!$check) {
        $pdo->exec("ALTER TABLE leads ADD COLUMN address VARCHAR(255) NULL AFTER location");
        echo "[OK] Added address to leads table\n";
    } else {
        echo "[OK] address already exists in leads table\n";
    }
} catch (Exception $e) {
    echo "[ERROR] address: " . $e->getMessage() . "\n";
}

require_once __DIR__ . '/migrate_workspaces.php';

echo "Migration completed successfully.\n";
