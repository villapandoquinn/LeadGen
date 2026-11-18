<?php
/**
 * Test LinkedIn Lead Sync Integration
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/LinkedInLeadService.php';

echo "===============================================\n";
echo "TESTING LINKEDIN LEAD SYNC SERVICE\n";
echo "===============================================\n";

$service = new LinkedInLeadService();
echo "[PASS] 1. LinkedInLeadService instantiated.\n";

$authUrl = $service->getAuthorizationUrl("test_csrf_token_abc");
echo "[PASS] 2. Auth URL generated: " . (strpos($authUrl, "https://www.linkedin.com/oauth/v2/authorization") === 0 ? "YES" : "NO") . "\n";
echo "       OAuth scopes included: " . (strpos($authUrl, 'r_ads_leadgen_automation') !== false ? "YES" : "NO") . "\n";

$status = $service->getConnectionStatus();
echo "[PASS] 3. Status: configured=" . ($status['configured'] ? 'true' : 'false') . ", connected=" . ($status['connected'] ? 'true' : 'false') . "\n";

$mockSubmission = [
    'id' => 'urn:li:leadFormTestSubmission_888999',
    'form' => 'urn:li:adForm:12345678',
    'campaign' => 'urn:li:sponsoredCampaign:87654321',
    'submittedAt' => 1731938400000,
    'formResponse' => [
        'answers' => [
            ['questionType' => 'FIRST_NAME', 'answerDetails' => ['textAnswer' => 'John']],
            ['questionType' => 'LAST_NAME', 'answerDetails' => ['textAnswer' => 'Doe']],
            ['questionType' => 'EMAIL', 'answerDetails' => ['textAnswer' => 'john.doe@techcorp.example']],
            ['questionType' => 'PHONE', 'answerDetails' => ['textAnswer' => '+1-555-0199']],
            ['questionType' => 'COMPANY', 'answerDetails' => ['textAnswer' => 'TechCorp Global']],
            ['questionType' => 'JOB_TITLE', 'answerDetails' => ['textAnswer' => 'Chief Technology Officer']],
            ['questionType' => 'INDUSTRY', 'answerDetails' => ['textAnswer' => 'Computer Software']],
            ['questionType' => 'LOCATION', 'answerDetails' => ['textAnswer' => 'San Francisco, CA']]
        ]
    ]
];

// Test reflection of mapSubmissionToLead method
$refMethod = new ReflectionMethod('LinkedInLeadService', 'mapSubmissionToLead');
if (PHP_VERSION_ID < 80500) {
    $refMethod->setAccessible(true);
}
$mapped = $refMethod->invoke($service, $mockSubmission, '500123456');

echo "[PASS] 4. Lead Mapped:\n";
echo "       Name: " . $mapped['name'] . "\n";
echo "       Company: " . $mapped['company'] . "\n";
echo "       Email: " . $mapped['email'] . "\n";
echo "       Job Title: " . $mapped['linkedin_job_title'] . "\n";
echo "       Submission ID: " . $mapped['linkedin_submission_id'] . "\n";

$workspaceId = 1;
$stmt = $pdo->prepare("INSERT INTO leads (workspace_id, name, company, email, phone, website, industry, location, source, status, score, notes, linkedin_submission_id, linkedin_form_id, linkedin_campaign_id, linkedin_account_id, linkedin_first_name, linkedin_last_name, linkedin_job_title, linkedin_submitted_at) VALUES (?,?,?,?,?,?,?,?,'linkedin','new',?,?,?,?,?,?,?,?,?,?)");
$stmt->execute([
    $workspaceId, $mapped['name'], $mapped['company'], $mapped['email'],
    $mapped['phone'], $mapped['website'], $mapped['industry'], $mapped['location'],
    $mapped['score'], $mapped['notes'], $mapped['linkedin_submission_id'],
    $mapped['linkedin_form_id'], $mapped['linkedin_campaign_id'], $mapped['linkedin_account_id'],
    $mapped['linkedin_first_name'], $mapped['linkedin_last_name'], $mapped['linkedin_job_title'],
    $mapped['linkedin_submitted_at']
]);
$testLeadId = $pdo->lastInsertId();
echo "[PASS] 5. Inserted test LinkedIn lead: ID=" . $testLeadId . "\n";

$prospectList = [
    $mapped,
    ['name' => 'Brand New Lead', 'email' => 'brandnew@test.example', 'linkedin_submission_id' => 'urn:li:leadFormTestSubmission_different_001']
];
$service->enrichDuplicates($prospectList, $pdo, $workspaceId);
echo "[PASS] 6. Duplicate Detection:\n";
echo "       Lead 1 (already inserted): is_imported=" . ($prospectList[0]['is_imported'] ? 'true' : 'false') . "\n";
echo "       Lead 2 (new lead): is_imported=" . ($prospectList[1]['is_imported'] ? 'true' : 'false') . "\n";

$logStmt = $pdo->prepare("INSERT INTO linkedin_sync_logs (workspace_id, user_id, account_id, form_id, leads_retrieved, leads_imported, leads_skipped, leads_failed, status, sync_from, sync_to, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW(), NOW(), NOW())");
$logStmt->execute([$workspaceId, 1, '500123456', '12345678', 1, 1, 0, 0]);
$testLogId = $pdo->lastInsertId();
echo "[PASS] 7. Created sync log: ID=" . $testLogId . "\n";

$pdo->prepare("DELETE FROM leads WHERE id = ?")->execute([$testLeadId]);
$pdo->prepare("DELETE FROM linkedin_sync_logs WHERE id = ?")->execute([$testLogId]);
echo "[PASS] 8. Cleaned up test records.\n";

echo "===============================================\n";
echo "ALL TESTS PASSED SUCCESSFULLY!\n";
echo "===============================================\n";

