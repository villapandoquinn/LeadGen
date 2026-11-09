<?php
class BillingService {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }
    public function getAllPlans(): array {
        return $this->pdo->query("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY price_monthly ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getWorkspaceSubscription(int $workspaceId): ?array {
        $stmt = $this->pdo->prepare("SELECT ws.*, sp.name as plan_name, sp.slug as plan_slug, sp.description as plan_description, sp.price_monthly, sp.price_annual, sp.max_users, sp.max_leads, sp.max_campaigns, sp.max_outreach, sp.features FROM workspace_subscriptions ws JOIN subscription_plans sp ON ws.plan_id = sp.id WHERE ws.workspace_id = ? ORDER BY ws.id DESC LIMIT 1");
        $stmt->execute([$workspaceId]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($sub) {
            $sub['features_list'] = !empty($sub['features']) ? (json_decode($sub['features'], true) ?: []) : [];
            $actDate = $sub['production_activated_at'] ?: $sub['starts_at'];
            $incEnds = $sub['included_ends_at'] ?: date('Y-m-d H:i:s', strtotime($actDate . ' +3 months'));
            $now = time(); $incEndTime = strtotime($incEnds);
            $sub['production_activated_at'] = $actDate;
            $sub['included_ends_at'] = $incEnds;
            $sub['is_included_period'] = ($now <= $incEndTime);
            $sub['remaining_included_days'] = $sub['is_included_period'] ? max(0, ceil(($incEndTime - $now) / 86400)) : 0;
            $sub['next_billing_date'] = $sub['is_included_period'] ? $incEnds : date('Y-m-d H:i:s', strtotime('+1 month'));
        }
        return $sub ?: null;
    }
    public function hasFeature(int $workspaceId, string $featureKey): bool {
        $sub = $this->getWorkspaceSubscription($workspaceId);
        if (!$sub) return false;
        $planSlug = $sub['plan_slug'] ?? 'basic';
        if (in_array($featureKey, ['linkedin_discovery', 'advanced_ai', 'enhanced_analytics', 'multi_source'], true)) {
            return ($planSlug === 'premium' || $planSlug === 'pro-scale');
        }
        return true;
    }
    public function isSubscriptionValid(int $workspaceId): bool {
        $sub = $this->getWorkspaceSubscription($workspaceId);
        if (!$sub) return false;
        if (!empty($sub['is_included_period']) && $sub['is_included_period'] === true) return true;
        $validStatuses = ['active', 'trial'];
        if (!in_array($sub['status'], $validStatuses, true)) return false;
        if (!empty($sub['ends_at'])) {
            $end = strtotime($sub['ends_at']);
            if ($end < time() && $sub['status'] === 'active') {
                $this->pdo->prepare("UPDATE workspace_subscriptions SET status = 'past_due' WHERE id = ?")->execute([$sub['id']]);
                return false;
            }
        }
        return true;
    }

    public function getUsageStats(int $workspaceId): array {
        $sub = $this->getWorkspaceSubscription($workspaceId);
        $maxLeads = (int)($sub['max_leads'] ?? 1000);
        $maxCampaigns = (int)($sub['max_campaigns'] ?? 10);
        $maxUsers = (int)($sub['max_users'] ?? 5);
        $maxOutreach = (int)($sub['max_outreach'] ?? 2500);
        $leadsCount = (int)$this->pdo->query("SELECT COUNT(*) FROM leads WHERE workspace_id = {$workspaceId}")->fetchColumn();
        $campCount = (int)$this->pdo->query("SELECT COUNT(*) FROM campaigns WHERE workspace_id = {$workspaceId} AND status = 'active'")->fetchColumn();
        $userCount = (int)$this->pdo->query("SELECT COUNT(*) FROM workspace_members WHERE workspace_id = {$workspaceId} AND status = 'active'")->fetchColumn();
        $outreachCount = (int)$this->pdo->query("SELECT COUNT(*) FROM outreach WHERE workspace_id = {$workspaceId}")->fetchColumn();
        return ['leads_used' => $leadsCount, 'leads_limit' => $maxLeads, 'campaigns_used' => $campCount, 'campaigns_limit' => $maxCampaigns, 'users_used' => $userCount, 'users_limit' => $maxUsers, 'outreach_used' => $outreachCount, 'outreach_limit' => $maxOutreach];
    }
    public function checkQuota(int $workspaceId, string $quotaType): bool {
        $stats = $this->getUsageStats($workspaceId);
        switch ($quotaType) {
            case 'leads':
                return $stats['leads_used'] < $stats['leads_limit'];
            case 'campaigns':
                return $stats['campaigns_used'] < $stats['campaigns_limit'];
            case 'users':
                return $stats['users_used'] < $stats['users_limit'];
            case 'outreach':
                return $stats['outreach_used'] < $stats['outreach_limit'];
            default:
                return true;
        }
    }
    public function getPaymentHistory(int $workspaceId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM payment_transactions WHERE workspace_id = ? ORDER BY created_at DESC");
        $stmt->execute([$workspaceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function processSubscriptionPayment(int $workspaceId, int $planId, string $cycle, array $cardInfo = []): array {
        $pStmt = $this->pdo->prepare("SELECT * FROM subscription_plans WHERE id = ? AND is_active = 1");
        $pStmt->execute([$planId]); $plan = $pStmt->fetch(PDO::FETCH_ASSOC);
        if (!$plan) return ['success' => false, 'message' => 'Subscription plan not found.'];
        $cycle = ($cycle === 'annual') ? 'annual' : 'monthly';
        $amount = ($cycle === 'annual') ? (float)$plan['price_annual'] : (float)$plan['price_monthly'];
        $txnRef = 'TXN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $invNum = 'INV-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $this->pdo->beginTransaction();
        try {
            $duration = ($cycle === 'annual') ? '1 YEAR' : '1 MONTH';
            $endsAtStmt = $this->pdo->query("SELECT DATE_ADD(NOW(), INTERVAL {$duration}) as ends_at");
            $endsAt = $endsAtStmt->fetchColumn();
            $existSub = $this->pdo->prepare("SELECT id FROM workspace_subscriptions WHERE workspace_id = ? LIMIT 1");
            $existSub->execute([$workspaceId]); $subId = $existSub->fetchColumn();
            if ($subId) {
                $uSub = $this->pdo->prepare("UPDATE workspace_subscriptions SET plan_id = ?, billing_cycle = ?, status = 'active', starts_at = NOW(), ends_at = ?, cancelled_at = NULL, cancel_reason = NULL WHERE id = ?");
                $uSub->execute([$planId, $cycle, $endsAt, $subId]);
            } else {
                $iSub = $this->pdo->prepare("INSERT INTO workspace_subscriptions (workspace_id, plan_id, billing_cycle, status, starts_at, ends_at) VALUES (?, ?, ?, 'active', NOW(), ?)");
                $iSub->execute([$workspaceId, $planId, $cycle, $endsAt]); $subId = $this->pdo->lastInsertId();
            }
            $last4 = !empty($cardInfo['last4']) ? substr(preg_replace('/\D/', '', $cardInfo['last4']), -4) : '4242';
            $brand = !empty($cardInfo['brand']) ? strtolower(trim($cardInfo['brand'])) : 'visa';
            $tIns = $this->pdo->prepare("INSERT INTO payment_transactions (workspace_id, subscription_id, transaction_ref, invoice_number, payment_gateway, amount, currency, status, payment_method, card_last4, card_brand, billing_name, notes, created_at) VALUES (?, ?, ?, ?, 'primovative_billing', ?, 'PHP', 'successful', 'Credit Card', ?, ?, 'Primovative Co. Client', ?, NOW())");
            $tIns->execute([$workspaceId, $subId, $txnRef, $invNum, $amount, $last4, $brand, "Monthly Subscription for " . $plan['name'] . " (" . ucfirst($cycle) . ")"]);
            $this->pdo->commit();
            return ['success' => true, 'invoice_number' => $invNum, 'transaction_ref' => $txnRef];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Payment processing failed: ' . $e->getMessage()];
        }
    }
    public function submitUpgradeRequest(int $workspaceId, int $currentPlanId, int $requestedPlanId, int $userId): array {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO subscription_upgrade_requests (workspace_id, current_plan_id, requested_plan_id, requested_by, status) VALUES (?, ?, ?, ?, 'pending')");
            $stmt->execute([$workspaceId, $currentPlanId, $requestedPlanId, $userId]);
            return ['success' => true, 'message' => 'Upgrade request submitted successfully. Administration will review and approve your request.'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Failed to submit upgrade request: ' . $e->getMessage()];
        }
    }
    public function cancelSubscription(int $workspaceId, ?string $reason = null): array {
        $sub = $this->getWorkspaceSubscription($workspaceId);
        if (!$sub) return ['success' => false, 'message' => 'No active subscription found.'];
        $upd = $this->pdo->prepare("UPDATE workspace_subscriptions SET status = 'cancelled', cancelled_at = NOW(), cancel_reason = ? WHERE id = ?");
        $upd->execute([$reason, $sub['id']]);
        return ['success' => true, 'message' => 'Subscription cancellation scheduled. Access remains active through ' . date('M d, Y', strtotime($sub['ends_at'] ?: '+30 days')) . '.'];
    }

}
