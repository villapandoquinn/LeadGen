<?php
/**
 * LinkedIn Lead Sync AJAX API -- linkedin/api.php
 * Actions: get_status | get_accounts | get_forms | sync_leads | get_logs
 * API Version: 202509 (LinkedIn Lead Sync API)
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../services/LinkedInLeadService.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid method.']); exit; }
if (!validate_csrf()) { echo json_encode(['success'=>false,'message'=>'CSRF failed. Refresh page.']); exit; }
$action  = trim($_POST['action'] ?? '');
$service = new LinkedInLeadService();
$connSts = $service->getConnectionStatus();
if (in_array($action,['get_accounts','get_forms','sync_leads']) && !$connSts['is_connected']) {
    echo json_encode(['success'=>false,'oauth_required'=>true,'message'=>'LinkedIn not connected. Authorize first.']); exit;
}
switch ($action) {
    case 'get_status':
        echo json_encode(['success'=>true,'status'=>$connSts]); break;
    case 'get_accounts':
        $r=$service->getAdAccounts();
        if (!$r['success']) { echo json_encode(['success'=>false,'message'=>$r['message']]); break; }
        $a=array_map(fn($x)=>['id'=>$x['id']??'','name'=>$x['name']??'Account','status'=>$x['status']??''],$r['data']['elements']??[]);
        echo json_encode(['success'=>true,'accounts'=>$a]); break;
    case 'get_forms':
        $aid=trim($_POST['account_id']??'');
        if (!$aid){echo json_encode(['success'=>false,'message'=>'Account ID required.']);break;}
        $r=$service->getLeadForms($aid);
        if (!$r['success']){echo json_encode(['success'=>false,'message'=>$r['message']]);break;}
        $f=array_map(fn($x)=>['id'=>$x['id']??'','name'=>$x['name']??'Form'],$r['data']['elements']??[]);
        echo json_encode(['success'=>true,'forms'=>$f]); break;
    case 'sync_leads':
        if (!$workspaceService->hasPermission($workspaceRole,'manage_leads')){echo json_encode(['success'=>false,'message'=>'No permission to import leads.']);break;}
        if (!$billingService->checkQuota($currentWorkspaceId,'leads')){echo json_encode(['success'=>false,'message'=>'Lead quota reached. Upgrade plan.']);break;}
        require_once __DIR__ . '/api_sync.php'; break;
    case 'get_logs':
        try{
            $ls=$pdo->prepare("SELECT l.*,u.name user_name FROM linkedin_sync_logs l LEFT JOIN users u ON l.user_id=u.id WHERE l.workspace_id=? ORDER BY l.created_at DESC LIMIT 25");
            $ls->execute([$currentWorkspaceId]);
            echo json_encode(['success'=>true,'logs'=>$ls->fetchAll()]);
        } catch(Throwable $e){echo json_encode(['success'=>false,'message'=>'Could not load logs.']);}
        break;
    default: echo json_encode(['success'=>false,'message'=>'Unknown action.']);
}
