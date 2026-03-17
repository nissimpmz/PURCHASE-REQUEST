<?php
require_once 'config.php';

$date = $_GET['date'] ?? date('Y-m-d');

$response = [
    'pr_number' => generatePRNumber($date),
    'po_number' => generatePONumber($date)
];

header('Content-Type: application/json');
echo json_encode($response);
?>