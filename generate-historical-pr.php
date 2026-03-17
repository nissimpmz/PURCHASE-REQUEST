<?php
// generate-historical-pr.php
require_once 'config.php';

header('Content-Type: application/json');

try {
    $date = $_GET['date'] ?? date('Y-m-d');
    $manualBase = $_GET['base'] ?? null;
    
    if (!$manualBase) {
        throw new Exception('No PR base number provided');
    }
    
    // Validate base format
    if (!preg_match('/^\d{4}-\d{2}-\d{3}$/', $manualBase)) {
        throw new Exception('Invalid PR base number format. Use YYYY-MM-NNN (e.g., 2025-01-007)');
    }
    
    $conn = getConnection();
    
    // Check existing PR numbers with this base
    $sql = "SELECT pr_number FROM purchase_requests 
            WHERE pr_number LIKE :base_pattern 
            ORDER BY pr_number ASC 
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $basePattern = $manualBase . '%';
    $stmt->bindParam(':base_pattern', $basePattern);
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        $lastPR = $existing['pr_number'];
        $baseLength = strlen($manualBase);
        $suffix = substr($lastPR, $baseLength);
        
        if (empty($suffix)) {
            // No suffix yet, add 'A'
            $newPR = $manualBase . 'A';
        } else {
            // Has suffix, increment letter
            $lastChar = $suffix[0];
            if ($lastChar >= 'A' && $lastChar < 'Z') {
                $nextChar = chr(ord($lastChar) + 1);
                $newPR = $manualBase . $nextChar;
            } elseif ($lastChar == 'Z') {
                // If Z, start double letters
                if (strlen($suffix) == 1) {
                    $newPR = $manualBase . 'AA';
                } else {
                    // Handle multiple letters (AA, AB, etc.)
                    $lastTwo = substr($suffix, -2);
                    if ($lastTwo == 'ZZ') {
                        throw new Exception('Maximum suffix reached for this PR number');
                    }
                    
                    $secondLast = $suffix[-2] ?? 'A';
                    $last = $suffix[-1];
                    
                    if ($last < 'Z') {
                        $newLast = chr(ord($last) + 1);
                        $newPR = $manualBase . $secondLast . $newLast;
                    } else {
                        $newSecond = chr(ord($secondLast) + 1);
                        $newPR = $manualBase . $newSecond . 'A';
                    }
                }
            } else {
                throw new Exception('Invalid suffix format');
            }
        }
    } else {
        // Base doesn't exist, use it as is
        $newPR = $manualBase;
    }
    
    echo json_encode([
        'success' => true,
        'pr_number' => $newPR,
        'base' => $manualBase,
        'date' => $date
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>