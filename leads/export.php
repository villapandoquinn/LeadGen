<?php
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=leads_export_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');

fputcsv($output, ['ID', 'Company', 'Contact Name', 'Email', 'Phone', 'Website', 'Industry', 'Location', 'Source', 'Status', 'Score', 'Campaign', 'Created At']);

$stmt = $pdo->prepare("
    SELECT l.id, l.company, l.name, l.email, l.phone, l.website, l.industry, l.location, l.source, l.status, l.score, c.name as campaign_name, l.created_at
    FROM leads l
    LEFT JOIN campaigns c ON l.campaign_id = c.id
    WHERE l.workspace_id = ?
    ORDER BY l.created_at DESC
");
$stmt->execute([$currentWorkspaceId]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, $row);
}

fclose($output);
exit;

