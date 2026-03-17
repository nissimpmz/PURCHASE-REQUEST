<?php
// fix-pr-ordering.php - Fix PR number ordering in the database
require_once 'config.php';

echo "Starting PR number ordering fix...\n\n";

try {
    $conn = getConnection();
    
    // Get all purchase requests ordered by date and proper PR number components
    $sql = "SELECT id, pr_number, date FROM purchase_requests 
            ORDER BY 
                CAST(SUBSTRING_INDEX(pr_number, '-', 1) AS UNSIGNED) ASC,
                CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(pr_number, '-', 2), '-', -1) AS UNSIGNED) ASC,
                CAST(SUBSTRING_INDEX(pr_number, '-', -1) AS UNSIGNED) ASC,
                pr_number ASC";
    
    $stmt = $conn->query($sql);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Current PR numbers in correct order:\n";
    echo str_repeat("-", 40) . "\n";
    
    foreach ($requests as $index => $request) {
        echo sprintf("%3d. %s (Date: %s)\n", 
            $index + 1, 
            $request['pr_number'], 
            $request['date']
        );
    }
    
    echo "\n" . str_repeat("-", 40) . "\n";
    echo "Total records: " . count($requests) . "\n";
    
    echo "\nThe ordering has been fixed in the queries.\n";
    echo "No database changes were made - this is just a verification.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nDone.\n";
?>