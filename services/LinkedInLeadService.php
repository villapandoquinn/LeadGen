<?php
/**
 * LinkedInLeadService -- LinkedIn Lead Sync API Integration
 * ProspectHunt / Primovative Co.
 *
 * Lead Sync API (Product version 202509 per screenshot) endpoints:
 *   FINDER /rest/adAccounts                             r_ads  Member(3-legged)
 *   GET    /rest/adAccounts/{id}                        r_ads  Member(3-legged)
 *   FINDER /rest/adAccounts/{id}/adCampaignGroups       r_ads  Member(3-legged)
 *   BATCH_GET /rest/adAccounts/{id}/adCampaignGroups    r_ads  Member(3-legged)
 *   BATCH_GET /rest/adAccounts/{id}/adCampaigns         r_ads  Member(3-legged)
 *   GET    /rest/leadForms                            r_ads_leadgen_automation
 *   GET    /rest/leadFormSubmissions                  r_ads_leadgen_automation
 *
 * OAuth: 3-legged Member authorization
 * Tokens stored ONLY in config/settings.json server-side -- never exposed to client
 */
class LinkedInLeadService {
    const API_BASE        = 'https://api.linkedin.com';
    const API_VERSION     = '202509';
    const OAUTH_BASE      = 'https://www.linkedin.com/oauth/v2';
    const REQUIRED_SCOPES = ['r_ads', 'r_ads_leadgen_automation', 'r_basicprofile'];

    private ?string $accessToken  = null;
    private ?string $refreshToken = null;
    private ?int    $tokenExpiry  = null;
    private ?string $clientId     = null;
    private ?string $clientSecret = null;
    private ?string $redirectUri  = null;
    private string  $settingsFile;

    public function __construct(?string $accessToken = null) {
        $this->settingsFile = __DIR__ . '/../config/settings.json';
        $this->loadCredentials();
        if (!empty($accessToken)) $this->accessToken = trim($accessToken);
    }

    private function loadCredentials(): void {
        if (!empty(getenv('LINKEDIN_ACCESS_TOKEN')))  $this->accessToken  = trim(getenv('LINKEDIN_ACCESS_TOKEN'));
        if (!empty(getenv('LINKEDIN_REFRESH_TOKEN'))) $this->refreshToken = trim(getenv('LINKEDIN_REFRESH_TOKEN'));
        if (!empty(getenv('LINKEDIN_TOKEN_EXPIRY')))  $this->tokenExpiry  = (int)getenv('LINKEDIN_TOKEN_EXPIRY');
        if (!empty(getenv('LINKEDIN_CLIENT_ID')))     $this->clientId     = trim(getenv('LINKEDIN_CLIENT_ID'));
        if (!empty(getenv('LINKEDIN_CLIENT_SECRET'))) $this->clientSecret = trim(getenv('LINKEDIN_CLIENT_SECRET'));
        if (!empty(getenv('LINKEDIN_REDIRECT_URI')))  $this->redirectUri  = trim(getenv('LINKEDIN_REDIRECT_URI'));
        if (file_exists($this->settingsFile)) {
            $s = json_decode(file_get_contents($this->settingsFile), true) ?? [];
            if (empty($this->accessToken)  && !empty($s['linkedin_access_token']))  $this->accessToken  = trim($s['linkedin_access_token']);
            if (empty($this->refreshToken) && !empty($s['linkedin_refresh_token'])) $this->refreshToken = trim($s['linkedin_refresh_token']);
            if (empty($this->tokenExpiry)  && !empty($s['linkedin_token_expiry']))  $this->tokenExpiry  = (int)$s['linkedin_token_expiry'];
            if (empty($this->clientId)     && !empty($s['linkedin_client_id']))     $this->clientId     = trim($s['linkedin_client_id']);
            if (empty($this->clientSecret) && !empty($s['linkedin_client_secret'])) $this->clientSecret = trim($s['linkedin_client_secret']);
            if (empty($this->redirectUri)  && !empty($s['linkedin_redirect_uri']))  $this->redirectUri  = trim($s['linkedin_redirect_uri']);
        }
    }

    private function persistTokens(string $token, ?string $refresh, int $expiresIn): void {
        $this->accessToken = $token; $this->refreshToken = $refresh;
        $this->tokenExpiry = time() + $expiresIn - 60;
        if (file_exists($this->settingsFile)) {
            $c = json_decode(file_get_contents($this->settingsFile), true) ?: [];
            $c['linkedin_access_token']  = $this->accessToken;
            $c['linkedin_refresh_token'] = $this->refreshToken ?? '';
            $c['linkedin_token_expiry']  = $this->tokenExpiry;
            $c['linkedin_connected_at']  = date('Y-m-d H:i:s');
            file_put_contents($this->settingsFile, json_encode($c, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        }
    }

    public function revokeTokens(): void {
        $this->accessToken = $this->refreshToken = null; $this->tokenExpiry = null;
        if (file_exists($this->settingsFile)) {
            $c = json_decode(file_get_contents($this->settingsFile), true) ?: [];
            $c['linkedin_access_token'] = $c['linkedin_refresh_token'] = '';
            $c['linkedin_token_expiry'] = 0; $c['linkedin_connected_at'] = '';
            file_put_contents($this->settingsFile, json_encode($c, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        }
    }


    public function isConfigured(): bool { return !empty($this->clientId) && !empty($this->clientSecret); }
    public function isConnected(): bool  { return !empty($this->accessToken) && !$this->isTokenExpired(); }
    public function isTokenExpired(): bool {
        if (empty($this->accessToken)) return true;
        if (empty($this->tokenExpiry)) return false;
        return time() >= $this->tokenExpiry;
    }
    public function getClientId(): ?string    { return $this->clientId; }
    public function getAccessToken(): ?string { return $this->accessToken; }

    public function getConnectionStatus(): array {
        $s = file_exists($this->settingsFile) ? (json_decode(file_get_contents($this->settingsFile), true) ?? []) : [];
        return [
            'is_configured' => $this->isConfigured(),
            'is_connected'  => $this->isConnected(),
            'is_expired'    => $this->isTokenExpired(),
            'has_token'     => !empty($this->accessToken),
            'connected_at'  => $s['linkedin_connected_at'] ?? null,
            'client_id_hint'=> !empty($this->clientId) ? substr($this->clientId, 0, 6) . '...' : null,
        ];
    }

    public function getAuthorizationUrl(string $state): string {
        return self::OAUTH_BASE . '/authorization?' . http_build_query([
            'response_type' => 'code',  'client_id'   => $this->clientId,
            'redirect_uri'  => $this->redirectUri, 'state' => $state,
            'scope'         => implode(' ', self::REQUIRED_SCOPES),
        ]);
    }

    public function handleOAuthCallback(string $code): array {
        if (empty($this->clientId) || empty($this->clientSecret) || empty($this->redirectUri)) {
            return ['success' => false, 'message' => 'LinkedIn OAuth credentials incomplete. Configure Client ID, Secret, and Redirect URI in Settings.'];
        }
        $ch = curl_init(self::OAUTH_BASE . '/accessToken');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'authorization_code', 'code' => $code,
                'redirect_uri' => $this->redirectUri,
                'client_id' => $this->clientId, 'client_secret' => $this->clientSecret,
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); $curlErr = curl_error($ch); curl_close($ch);
        if ($curlErr) return ['success' => false, 'message' => 'Network error during LinkedIn OAuth: ' . $curlErr];
        $data = json_decode($response, true);
        if ($httpCode !== 200 || empty($data['access_token'])) {
            $msg = $data['error_description'] ?? ($data['error'] ?? 'OAuth token exchange failed.');
            error_log('[LinkedIn OAuth] HTTP ' . $httpCode . ': ' . $msg);
            return ['success' => false, 'message' => 'LinkedIn authorization failed: ' . $msg];
        }
        $this->persistTokens($data['access_token'], $data['refresh_token'] ?? null, (int)($data['expires_in'] ?? 5184000));
        return ['success' => true, 'message' => 'LinkedIn account connected successfully!'];
    }

    public function refreshAccessToken(): bool {
        if (empty($this->refreshToken) || empty($this->clientId) || empty($this->clientSecret)) return false;
        $ch = curl_init(self::OAUTH_BASE . '/accessToken');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'refresh_token', 'refresh_token' => $this->refreshToken,
                'client_id' => $this->clientId, 'client_secret' => $this->clientSecret,
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $data = json_decode($response, true);
        if ($httpCode === 200 && !empty($data['access_token'])) {
            $this->persistTokens($data['access_token'], $data['refresh_token'] ?? $this->refreshToken, (int)($data['expires_in'] ?? 5184000));
            return true;
        }
        return false;
    }


    /** Authenticated GET with LinkedIn-Version: 202509 header */
    private function apiGet(string $endpoint, array $params = [], bool $retry = false): array {
        if ($this->isTokenExpired() && !empty($this->refreshToken) && !$retry) {
            if (!$this->refreshAccessToken()) return ['success'=>false,'http_code'=>401,'message'=>'LinkedIn token expired. Reconnect.','data'=>null];
        }
        if (empty($this->accessToken)) return ['success'=>false,'http_code'=>401,'message'=>'No LinkedIn access token. Connect your account.','data'=>null];
        $url = self::API_BASE . $endpoint . (!empty($params) ? '?' . http_build_query($params) : '');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'LinkedIn-Version: ' . self::API_VERSION,
                'X-Restli-Protocol-Version: 2.0.0',
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        if ($err) { error_log('[LinkedIn] cURL: '.$err); return ['success'=>false,'http_code'=>0,'message'=>'Network error connecting to LinkedIn.','data'=>null]; }
        $data = json_decode($resp, true);
        if ($code === 401 && !$retry && !empty($this->refreshToken) && $this->refreshAccessToken()) return $this->apiGet($endpoint,$params,true);
        if ($code === 401) return ['success'=>false,'http_code'=>401,'message'=>'LinkedIn auth expired. Reconnect your account.','data'=>null];
        if ($code === 403) return ['success'=>false,'http_code'=>403,'message'=>'Insufficient LinkedIn permissions. Ensure r_ads + r_ads_leadgen_automation scopes approved.','data'=>null];
        if ($code === 429) return ['success'=>false,'http_code'=>429,'message'=>'LinkedIn rate limit reached. Wait before retrying.','data'=>null];
        if ($code < 200 || $code >= 300) { $m=$data['message']??($data['error']??'HTTP '.$code); error_log('[LinkedIn] '.$code.': '.$m); return ['success'=>false,'http_code'=>$code,'message'=>'LinkedIn API error: '.$m,'data'=>null]; }
        return ['success'=>true,'http_code'=>$code,'message'=>'OK','data'=>$data];
    }

    public function testKey(?string $token = null): array {
        if (!empty($token)) $this->accessToken = trim($token);
        if (empty($this->accessToken) && empty($this->clientId)) return ['success'=>false,'message'=>'No LinkedIn credentials configured.'];
        if (!empty($this->accessToken)) {
            $r = $this->apiGet('/v2/me');
            if ($r['success']) { $n=trim(($r['data']['localizedFirstName']??'').' '.($r['data']['localizedLastName']??'')); return ['success'=>true,'message'=>'LinkedIn connected! Account: '.($n?:'Verified')]; }
            return ['success'=>false,'message'=>$r['message']];
        }
        return ['success'=>false,'message'=>'LinkedIn not yet authorized. Complete the OAuth flow.'];
    }

    /** /rest/adAccounts FINDER -- r_ads -- v202509 */
    public function getAdAccounts(): array {
        return $this->apiGet('/rest/adAccounts',['q'=>'search','search.type.values[0]'=>'BUSINESS','search.status.values[0]'=>'ACTIVE']);
    }
    /** /rest/adAccounts/{id}/adCampaigns BATCH_GET -- r_ads -- v202509 */
    public function getAdCampaigns(string $id): array {
        return $this->apiGet('/rest/adAccounts/'.$id.'/adCampaigns',['q'=>'search','search.status.values[0]'=>'ACTIVE']);
    }
    /** /rest/adForms -- r_ads_leadgen_automation -- v202509 */
    public function getLeadForms(string $id): array {
        return $this->apiGet('/rest/adForms',['q'=>'account','account'=>'urn:li:sponsoredAccount:'.$id]);
    }
    /**
     * GET /rest/leadFormSubmissions -- PRIMARY Lead Sync endpoint
     * Scope: r_ads_leadgen_automation | LinkedIn-Version: 202509
     */
    public function getLeadFormSubmissions(string $acctId, ?string $formId=null, ?int $startMs=null, int $count=100, int $start=0): array {
        $p = ['q'=>'owner','owner'=>'urn:li:sponsoredAccount:'.$acctId,'count'=>min($count,100),'start'=>$start];
        if ($formId)  $p['form']                = 'urn:li:adForm:'.$formId;
        if ($startMs) $p['submissionStartedAt'] = $startMs;
        return $this->apiGet('/rest/leadFormSubmissions', $p);
    }

    public function syncLeads(string $acctId, ?string $formId=null, ?int $lastTs=null): array {
        if (!$this->isConnected()) return ['success'=>false,'leads'=>[],'total'=>0,'message'=>'LinkedIn not connected. Authorize via OAuth.'];
        $all=[]; $start=0; $ps=100; $maxP=20; $pg=0;
        $startMs = $lastTs ? $lastTs*1000 : null;
        do {
            $r = $this->getLeadFormSubmissions($acctId,$formId,$startMs,$ps,$start);
            if (!$r['success']) return ['success'=>false,'leads'=>$all,'total'=>count($all),'message'=>$r['message']];
            $els = $r['data']['elements'] ?? [];
            if (!$els) break;
            foreach ($els as $sub) $all[] = $this->mapSubmissionToLead($sub,$acctId);
            $tot = $r['data']['paging']['total'] ?? count($all);
            $start+=$ps; $pg++;
            if (count($els)<$ps || $start>=$tot) break;
        } while ($pg<$maxP);
        return ['success'=>true,'leads'=>$all,'total'=>count($all),'message'=>'Retrieved '.count($all).' lead(s) from LinkedIn.'];
    }


    private function mapSubmissionToLead(array $sub, string $acctId): array {
        $ans = [];
        foreach ($sub['formResponse']['answers'] ?? [] as $a) {
            $qt = strtoupper($a['questionType'] ?? '');
            $v  = trim($a['answerDetails']['textAnswer'] ?? '');
            if ($qt && $v) $ans[$qt] = $v;
        }
        $first = $ans['FIRST_NAME'] ?? '';
        $last  = $ans['LAST_NAME']  ?? '';
        $full  = trim("$first $last") ?: ($ans['FULL_NAME'] ?? '');
        $email = $ans['EMAIL'] ?? ($ans['WORK_EMAIL'] ?? '');
        $phone = $ans['PHONE'] ?? ($ans['PHONE_NUMBER'] ?? '');
        $co    = $ans['COMPANY'] ?? ($ans['COMPANY_NAME'] ?? '');
        $title = $ans['JOB_TITLE'] ?? ($ans['TITLE'] ?? '');
        $subMs = $sub['submittedAt'] ?? (time() * 1000);
        return [
            // Maps to existing leads table columns
            'name'     => $full ?: ($co ?: 'LinkedIn Lead'),
            'company'  => $co ?: $full,
            'email'    => !empty($email) ? strtolower(trim($email)) : null,
            'phone'    => $phone,
            'industry' => $ans['INDUSTRY'] ?? '',
            'location' => $ans['LOCATION'] ?? '',
            'website'  => $ans['WEBSITE']  ?? '',
            'source'   => 'linkedin',
            'score'    => 80,
            'notes'    => $title ? 'Job Title: ' . $title : '',
            // LinkedIn-specific identifiers (require migration columns)
            'linkedin_submission_id' => $sub['id'] ?? '',
            'linkedin_form_id'       => ltrim($sub['form']     ?? '', 'urn:li:adForm:'),
            'linkedin_campaign_id'   => ltrim($sub['campaign'] ?? '', 'urn:li:sponsoredCampaign:'),
            'linkedin_account_id'    => $acctId,
            'linkedin_first_name'    => $first,
            'linkedin_last_name'     => $last,
            'linkedin_job_title'     => $title,
            'linkedin_submitted_at'  => date('Y-m-d H:i:s', (int)($subMs / 1000)),
        ];
    }

    public function enrichDuplicates(array &$prospects, PDO $pdo, int $workspaceId): void {
        if (empty($prospects)) return;
        $sids   = array_values(array_filter(array_column($prospects, 'linkedin_submission_id')));
        $emails = array_values(array_filter(array_map(fn($p) => strtolower($p['email'] ?? ''), $prospects)));
        $bySid  = []; $byEmail = [];
        if (!empty($sids)) {
            try {
                $in = implode(',', array_fill(0, count($sids), '?'));
                $st = $pdo->prepare("SELECT id, linkedin_submission_id FROM leads WHERE workspace_id=? AND linkedin_submission_id IN ($in)");
                $st->execute(array_merge([$workspaceId], $sids));
                foreach ($st->fetchAll() as $r) $bySid[$r['linkedin_submission_id']] = (int)$r['id'];
            } catch (Throwable $e) {}
        }
        if (!empty($emails)) {
            try {
                $in = implode(',', array_fill(0, count($emails), '?'));
                $st = $pdo->prepare("SELECT id, email FROM leads WHERE workspace_id=? AND email IN ($in)");
                $st->execute(array_merge([$workspaceId], $emails));
                foreach ($st->fetchAll() as $r) $byEmail[strtolower($r['email'])] = (int)$r['id'];
            } catch (Throwable $e) {}
        }
        foreach ($prospects as &$p) {
            $p['is_imported'] = false; $p['existing_lead_id'] = null;
            $sid = $p['linkedin_submission_id'] ?? ''; $em = strtolower($p['email'] ?? '');
            if (!empty($sid) && isset($bySid[$sid])) { $p['is_imported'] = true; $p['existing_lead_id'] = $bySid[$sid]; }
            elseif (!empty($em) && isset($byEmail[$em])) { $p['is_imported'] = true; $p['existing_lead_id'] = $byEmail[$em]; }
        }
        unset($p);
    }

    public function qualifyProspects(array &$prospects, array $inc = [], array $exc = [], string $ind = ''): void {
        foreach ($prospects as &$p) { $p['qualification'] = 'Highly Relevant'; $p['score'] = $p['score'] ?? 80; }
        unset($p);
    }

    public function logDiscovery(PDO $pdo, int $wid, ?int $cid, int $uid, string $q, int $cnt): void {
        try { $st = $pdo->prepare("INSERT INTO discovery_logs (workspace_id,campaign_id,user_id,search_query,results_count) VALUES(?,?,?,?,?)"); $st->execute([$wid,$cid,$uid,'LinkedIn: '.substr($q,0,245),$cnt]); } catch (Throwable $e) {}
    }

    /** Backward-compatible searchLeads for lead-discovery/index.php */
    public function searchLeads(string $query, array $options = []): array {
        if (!$this->isConfigured()) return ['success'=>false,'configured'=>false,'message'=>'LinkedIn API not configured. Add Client ID & Secret in Settings.','results'=>[]];
        if (!$this->isConnected()) return ['success'=>false,'configured'=>true,'oauth_required'=>true,'message'=>'LinkedIn not connected. Use LinkedIn Lead Sync to authorize.','results'=>[]];
        return ['success'=>true,'configured'=>true,'oauth_required'=>false,'results'=>[],'message'=>'Use LinkedIn Lead Sync page to retrieve lead form submissions.','next_page_token'=>null];
    }
}

