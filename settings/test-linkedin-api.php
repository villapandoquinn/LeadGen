<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../services/LinkedInLeadService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$accessToken = trim($_POST['access_token'] ?? '');
$service = new LinkedInLeadService($accessToken);
$res = $service->testKey($accessToken);

echo json_encode($res);
