<?php
/**
 * WorkspaceService - Multi-Workspace Architecture & Access Control
 */
class WorkspaceService {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getUserWorkspaces(int $userId): array {
        $stmt = $this->pdo->prepare("
            SELECT w.*, wm.role as member_role,
                   (SELECT COUNT(*) FROM workspace_members WHERE workspace_id = w.id AND status = 'active') as member_count
            FROM workspaces w
            JOIN workspace_members wm ON w.id = wm.workspace_id
            WHERE wm.user_id = ? AND wm.status = 'active' AND w.status != 'archived'
            ORDER BY (w.id = 1) DESC, w.name ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCurrentWorkspace(int $userId): array {
        $currentId = (int)($_SESSION['current_workspace_id'] ?? 0);
        if ($currentId > 0) {
            $stmt = $this->pdo->prepare("
                SELECT w.*, wm.role as member_role
                FROM workspaces w
                JOIN workspace_members wm ON w.id = wm.workspace_id
                WHERE w.id = ? AND wm.user_id = ? AND wm.status = 'active' AND w.status != 'archived'
                LIMIT 1
            ");
            $stmt->execute([$currentId, $userId]);
            $ws = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($ws) return $ws;
        }

        $list = $this->getUserWorkspaces($userId);
        if (!empty($list)) {
            $_SESSION['current_workspace_id'] = (int)$list[0]['id'];
            return $list[0];
        }

        $uStmt = $this->pdo->prepare("SELECT name FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $u = $uStmt->fetch(PDO::FETCH_ASSOC);
        $wsName = (!empty($u['name']) ? $u['name'] : 'Personal') . "'s Workspace";

        $newWsId = $this->createWorkspace($userId, $wsName, "Default personal workspace");
        $_SESSION['current_workspace_id'] = $newWsId;

        $stmt = $this->pdo->prepare("SELECT w.*, wm.role as member_role FROM workspaces w JOIN workspace_members wm ON w.id = wm.workspace_id WHERE w.id = ? AND wm.user_id = ? LIMIT 1");
        $stmt->execute([$newWsId, $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function switchWorkspace(int $userId, int $workspaceId): bool {
        $stmt = $this->pdo->prepare("SELECT w.id FROM workspaces w JOIN workspace_members wm ON w.id = wm.workspace_id WHERE w.id = ? AND wm.user_id = ? AND wm.status = 'active' AND w.status != 'archived' LIMIT 1");
        $stmt->execute([$workspaceId, $userId]);
        if ($stmt->fetch()) {
            $_SESSION['current_workspace_id'] = $workspaceId;
            return true;
        }
        return false;
    }

    public function createWorkspace(int $ownerId, string $name, ?string $desc = null): int {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($name)));
        $slug = trim($slug, '-') ?: 'workspace';
        $base = $slug;
        $i = 1;
        while ($this->pdo->query("SELECT id FROM workspaces WHERE slug = " . $this->pdo->quote($slug))->fetch()) {
            $slug = $base . '-' . (++$i);
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("INSERT INTO workspaces (name, slug, description, owner_id, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->execute([trim($name), $slug, $desc, $ownerId]);
            $wsId = (int)$this->pdo->lastInsertId();

            $this->pdo->prepare("INSERT INTO workspace_members (workspace_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')")->execute([$wsId, $ownerId]);

            $pId = (int)($this->pdo->query("SELECT id FROM subscription_plans WHERE slug = 'starter' LIMIT 1")->fetchColumn() ?: 1);
            $this->pdo->prepare("INSERT INTO workspace_subscriptions (workspace_id, plan_id, billing_cycle, status, starts_at, ends_at) VALUES (?, ?, 'monthly', 'trial', NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY))")->execute([$wsId, $pId]);

            $this->pdo->commit();
            return $wsId;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function hasPermission(string $role, string $perm): bool {
        $matrix = [
            'owner'   => ['view', 'create', 'edit', 'delete', 'manage_campaigns', 'manage_leads', 'manage_outreach', 'billing', 'manage_members', 'manage_workspace', 'delete_workspace'],
            'admin'   => ['view', 'create', 'edit', 'delete', 'manage_campaigns', 'manage_leads', 'manage_outreach', 'billing', 'manage_members', 'manage_workspace'],
            'manager' => ['view', 'create', 'edit', 'delete', 'manage_campaigns', 'manage_leads', 'manage_outreach'],
            'member'  => ['view', 'create', 'edit', 'manage_campaigns', 'manage_leads', 'manage_outreach'],
            'viewer'  => ['view']
        ];
        return in_array($perm, $matrix[strtolower($role)] ?? [], true);
    }
    public function getMembers(int $workspaceId): array {
        $stmt = $this->pdo->prepare("SELECT wm.*, u.name, u.email, u.profile_picture, u.auth_provider, u.status as user_status FROM workspace_members wm JOIN users u ON wm.user_id = u.id WHERE wm.workspace_id = ? ORDER BY (wm.role = 'owner') DESC, (wm.role = 'admin') DESC, u.name ASC");
        $stmt->execute([$workspaceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addMember(int $workspaceId, string $email, string $role, ?string $name = null): array {
        $email = trim(strtolower($email));
        $validRoles = ['owner', 'admin', 'manager', 'member', 'viewer'];
        if (!in_array($role, $validRoles, true)) $role = 'member';

        $uStmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $uStmt->execute([$email]);
        $user = $uStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $userName = !empty($name) ? trim($name) : explode('@', $email)[0];
            $hash = password_hash('LeadFlow2026!', PASSWORD_DEFAULT);
            $this->pdo->prepare("INSERT INTO users (name, email, password_hash, auth_provider, status) VALUES (?, ?, ?, 'email', 'active')")->execute([$userName, $email, $hash]);
            $userId = (int)$this->pdo->lastInsertId();
        } else {
            $userId = (int)$user['id'];
        }

        $mCheck = $this->pdo->prepare("SELECT id, status FROM workspace_members WHERE workspace_id = ? AND user_id = ?");
        $mCheck->execute([$workspaceId, $userId]);
        $ex = $mCheck->fetch(PDO::FETCH_ASSOC);

        if ($ex) {
            if ($ex['status'] === 'inactive') {
                $this->pdo->prepare("UPDATE workspace_members SET status = 'active', role = ? WHERE id = ?")->execute([$role, $ex['id']]);
                return ['success' => true, 'message' => 'Member reactivated in workspace.'];
            }
            return ['success' => false, 'message' => 'User is already a member of this workspace.'];
        }

        $this->pdo->prepare("INSERT INTO workspace_members (workspace_id, user_id, role, status) VALUES (?, ?, ?, 'active')")->execute([$workspaceId, $userId, $role]);
        return ['success' => true, 'message' => 'Member added successfully.'];
    }

    public function removeMember(int $workspaceId, int $targetUserId, int $actorUserId): array {
        $tStmt = $this->pdo->prepare("SELECT role FROM workspace_members WHERE workspace_id = ? AND user_id = ?");
        $tStmt->execute([$workspaceId, $targetUserId]);
        $targetRole = $tStmt->fetchColumn();
        if (!$targetRole) return ['success' => false, 'message' => 'Member not found in workspace.'];

        if ($targetRole === 'owner') {
            $ownerCount = (int)$this->pdo->query("SELECT COUNT(*) FROM workspace_members WHERE workspace_id = {$workspaceId} AND role = 'owner' AND status = 'active'")->fetchColumn();
            if ($ownerCount <= 1) return ['success' => false, 'message' => 'Cannot remove the last workspace owner.'];
        }

        $this->pdo->prepare("DELETE FROM workspace_members WHERE workspace_id = ? AND user_id = ?")->execute([$workspaceId, $targetUserId]);
        return ['success' => true, 'message' => 'Member removed successfully.'];
    }

    public function updateMemberRole(int $workspaceId, int $targetUserId, string $newRole): array {
        $validRoles = ['owner', 'admin', 'manager', 'member', 'viewer'];
        if (!in_array($newRole, $validRoles, true)) return ['success' => false, 'message' => 'Invalid role.'];

        $tStmt = $this->pdo->prepare("SELECT role FROM workspace_members WHERE workspace_id = ? AND user_id = ?");
        $tStmt->execute([$workspaceId, $targetUserId]);
        $currentRole = $tStmt->fetchColumn();
        if (!$currentRole) return ['success' => false, 'message' => 'Member not in workspace.'];

        if ($currentRole === 'owner' && $newRole !== 'owner') {
            $ownerCount = (int)$this->pdo->query("SELECT COUNT(*) FROM workspace_members WHERE workspace_id = {$workspaceId} AND role = 'owner' AND status = 'active'")->fetchColumn();
            if ($ownerCount <= 1) return ['success' => false, 'message' => 'Cannot demote the last workspace owner.'];
        }

        $this->pdo->prepare("UPDATE workspace_members SET role = ? WHERE workspace_id = ? AND user_id = ?")->execute([$newRole, $workspaceId, $targetUserId]);
        return ['success' => true, 'message' => 'Member role updated.'];
    }


}
