<?php
// migrate-pr-numbers.php - Migration script for PR numbers
require_once 'config.php';

echo "Starting PR number migration from 4-digit to 3-digit format...\n\n";

try {
    $conn = getConnection();
    
    // Get all purchase requests ordered by date
    $sql = "SELECT id, pr_number, date FROM purchase_requests ORDER BY date ASC";
    $stmt = $conn->query($sql);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($requests)) {
        echo "No purchase requests found.\n";
        exit;
    }
    
    echo "Found " . count($requests) . " purchase requests to migrate.\n\n";
    
    // Group by year to maintain separate numbering per year
    $yearlyCounters = [];
    $migrationMap = [];
    
    // First pass: Calculate new PR numbers
    foreach ($requests as $request) {
        $year = date('Y', strtotime($request['date']));
        
        if (!isset($yearlyCounters[$year])) {
            // Start from 1 for each year
            $yearlyCounters[$year] = 1;
        }
        
        $oldPR = $request['pr_number'];
        $month = date('m', strtotime($request['date']));
        $newPR = sprintf("%04d-%02d-%03d", $year, $month, $yearlyCounters[$year]);
        
        $migrationMap[] = [
            'id' => $request['id'],
            'old_pr' => $oldPR,
            'new_pr' => $newPR
        ];
        
        $yearlyCounters[$year]++;
    }
    
    // Display preview
    echo "Migration Preview:\n";
    echo str_repeat("-", 60) . "\n";
    echo sprintf("%-5s %-15s %-15s\n", "ID", "Old PR", "New PR");
    echo str_repeat("-", 60) . "\n";
    
    $sampleCount = min(10, count($migrationMap));
    for ($i = 0; $i < $sampleCount; $i++) {
        echo sprintf("%-5d %-15s %-15s\n", 
            $migrationMap[$i]['id'], 
            $migrationMap[$i]['old_pr'], 
            $migrationMap[$i]['new_pr']
        );
    }
    
    if (count($migrationMap) > $sampleCount) {
        echo "... and " . (count($migrationMap) - $sampleCount) . " more\n";
    }
    
    echo "\n";
    
    // Ask for confirmation
    echo "Do you want to proceed with the migration? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $response = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($response) !== 'yes') {
        echo "Migration cancelled.\n";
        exit;
    }
    
    echo "\nStarting migration...\n";
    
    // Begin transaction
    $conn->beginTransaction();
    
    $updated = 0;
    $errors = 0;
    
    foreach ($migrationMap as $migration) {
        try {
            // Check if new PR number already exists
            $checkSql = "SELECT COUNT(*) as count FROM purchase_requests WHERE pr_number = ? AND id != ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$migration['new_pr'], $migration['id']]);
            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                echo "Error: PR number {$migration['new_pr']} already exists. Skipping ID {$migration['id']}\n";
                $errors++;
                continue;
            }
            
            // Update the PR number
            $updateSql = "UPDATE purchase_requests SET pr_number = ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->execute([$migration['new_pr'], $migration['id']]);
            
            if ($updateStmt->rowCount() > 0) {
                echo "Updated ID {$migration['id']}: {$migration['old_pr']} -> {$migration['new_pr']}\n";
                $updated++;
            }
            
        } catch (Exception $e) {
            echo "Error updating ID {$migration['id']}: " . $e->getMessage() . "\n";
            $errors++;
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo "\nMigration completed!\n";
    echo "Updated: $updated records\n";
    echo "Errors: $errors records\n";
    
    // Verify migration
    echo "\nVerifying migration...\n";
    $verifySql = "SELECT COUNT(*) as total, 
                         SUM(CASE WHEN LENGTH(pr_number) = 13 THEN 1 ELSE 0 END) as correct_format,
                         SUM(CASE WHEN LENGTH(pr_number) > 13 THEN 1 ELSE 0 END) as old_format
                  FROM purchase_requests";
    $verifyStmt = $conn->query($verifySql);
    $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Total records: " . $verifyResult['total'] . "\n";
    echo "Correct format (13 chars): " . $verifyResult['correct_format'] . "\n";
    echo "Old format (>13 chars): " . $verifyResult['old_format'] . "\n";
    
    if ($verifyResult['old_format'] > 0) {
        echo "\nWARNING: Some records still have old format. Check these IDs:\n";
        $problemSql = "SELECT id, pr_number FROM purchase_requests WHERE LENGTH(pr_number) > 13";
        $problemStmt = $conn->query($problemSql);
        $problems = $problemStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($problems as $problem) {
            echo "ID {$problem['id']}: {$problem['pr_number']}\n";
        }
    } else {
        echo "\nSUCCESS: All PR numbers are now in 3-digit format!\n";
    }
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "Migration failed: " . $e->getMessage() . "\n";
}

echo "\nDone.\n";
?>