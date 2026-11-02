<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../services/GooglePlacesService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$apiKey = trim($_POST['api_key'] ?? '');
if (empty($apiKey)) {
    echo json_encode(['success' => false, 'message' => 'API key is required.']);
    exit;
}

$service = new GooglePlacesService($apiKey);
$res = $service->testKey($apiKey);

echo json_encode($res);
