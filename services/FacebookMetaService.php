<?php
/**
 * FacebookMetaService
 * Official Facebook Graph API Integration for Business Discovery & Lead Generation
 */
class FacebookMetaService {
    private ?string $accessToken = null;
    private string $settingsFile;

    public function __construct(?string $accessToken = null) {
        $this->settingsFile = __DIR__ . '/../config/settings.json';
        if (!empty($accessToken)) {
            $this->accessToken = trim($accessToken);
        } else {
            $this->accessToken = $this->resolveAccessToken();
        }
    }

    private function resolveAccessToken(): ?string {
        $envToken = getenv('FACEBOOK_ACCESS_TOKEN') ?: getenv('META_ACCESS_TOKEN');
        if (!empty($envToken)) return trim($envToken);

        if (file_exists($this->settingsFile)) {
            $json = json_decode(file_get_contents($this->settingsFile), true);
            if (!empty($json['facebook_access_token'])) {
                return trim($json['facebook_access_token']);
            }
        }
        return null;
    }

    public function isConfigured(): bool {
        return !empty($this->accessToken);
    }

    public function getAccessToken(): ?string {
        return $this->accessToken;
    }

    public function testKey(?string $testToken = null): array {
        $token = !empty($testToken) ? trim($testToken) : $this->accessToken;
        if (empty($token)) {
            return ['success' => false, 'message' => 'No Facebook Meta Access Token provided.'];
        }

        $url = 'https://graph.facebook.com/v20.0/me?access_token=' . urlencode($token);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['success' => false, 'message' => 'cURL Error: ' . $curlErr];
        }

        $data = json_decode($response, true);
        if ($httpCode === 200 && isset($data['id'])) {
            return ['success' => true, 'message' => 'Facebook Meta API Access Token verified successfully!'];
        }

        $searchUrl = 'https://graph.facebook.com/v20.0/pages/search?q=coffee&limit=1&access_token=' . urlencode($token);
        $ch2 = curl_init($searchUrl);
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $resp2 = curl_exec($ch2);
        $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);

        if ($code2 === 200) {
            return ['success' => true, 'message' => 'Facebook Meta API Pages Search verified successfully!'];
        }

        $errMsg = $data['error']['message'] ?? 'Facebook Graph API rejected the token.';
        return ['success' => false, 'message' => 'Facebook API Authentication Failed: ' . $errMsg];
    }
    public function searchPages(string $query, array $options = []): array {
        if (!$this->isConfigured()) {
            return ['success' => false, 'configured' => false, 'message' => 'Facebook Meta Access Token is not configured.', 'results' => []];
        }

        $limit = $options['limit'] ?? 20;
        $url = 'https://graph.facebook.com/v20.0/pages/search?q=' . urlencode($query) . '&fields=id,name,category,about,phone,emails,website,location,link,fan_count&limit=' . (int)$limit . '&access_token=' . urlencode($this->accessToken);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['success' => false, 'configured' => true, 'message' => 'cURL Error: ' . $curlErr, 'results' => []];
        }

        $data = json_decode($response, true);

        if ($httpCode === 200 && isset($data['data'])) {
            $pages = $data['data'];
            $results = [];

            foreach ($pages as $p) {
                $pageId = $p['id'] ?? '';
                $name = $p['name'] ?? 'Unknown Business';
                $category = $p['category'] ?? 'Facebook Business Page';
                $about = $p['about'] ?? '';
                $phone = $p['phone'] ?? '';
                $email = !empty($p['emails'][0]) ? $p['emails'][0] : '';
                $website = $p['website'] ?? ($p['link'] ?? '');
                $fanCount = (int)($p['fan_count'] ?? 0);
                
                $address = '';
                if (!empty($p['location']) && is_array($p['location'])) {
                    $loc = $p['location'];
                    $parts = array_filter([
                        $loc['street'] ?? '',
                        $loc['city'] ?? '',
                        $loc['state'] ?? '',
                        $loc['country'] ?? ''
                    ]);
                    $address = implode(', ', $parts);
                }
                if (empty($address)) {
                    $address = $about ?: 'Facebook Location';
                }

                $rating = min(5.0, round(3.5 + min(1.5, $fanCount / 5000), 1));
                if ($fanCount === 0) $rating = 4.0;

                $results[] = [
                    'place_id' => 'fb_page_' . $pageId,
                    'company' => $name,
                    'name' => $name,
                    'category' => $category,
                    'address' => $address,
                    'phone' => $phone,
                    'email' => $email,
                    'website' => $website,
                    'rating' => $rating,
                    'reviews_count' => $fanCount,
                    'source' => 'facebook',
                    'score' => 75
                ];
            }

            return [
                'success' => true,
                'configured' => true,
                'count' => count($results),
                'results' => $results
            ];
        }

        $errorMsg = 'Failed to fetch business pages from Facebook Meta API.';
        if (!empty($data['error']['message'])) {
            $errorMsg = $data['error']['message'];
        }

        return ['success' => false, 'configured' => true, 'message' => $errorMsg, 'results' => []];
    }

    public function enrichDuplicates(array &$prospects, PDO $pdo, int $workspaceId = 0): void {
        if (empty($prospects)) return;

        $placeIds = array_values(array_filter(array_column($prospects, 'place_id')));
        $phones = array_values(array_filter(array_column($prospects, 'phone')));

        $existingByPlace = [];
        $existingByPhone = [];

        if (!empty($placeIds)) {
            $inClause = implode(',', array_fill(0, count($placeIds), '?'));
            $sql = "SELECT id, google_place_id, campaign_id FROM leads WHERE google_place_id IN ($inClause)";
            $params = $placeIds;
            if ($workspaceId > 0) {
                $sql .= " AND workspace_id = ?";
                $params[] = $workspaceId;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existingByPlace[$row['google_place_id']] = $row;
            }
        }

        if (!empty($phones)) {
            $inClause = implode(',', array_fill(0, count($phones), '?'));
            $sql = "SELECT id, phone, campaign_id FROM leads WHERE phone IN ($inClause)";
            $params = $phones;
            if ($workspaceId > 0) {
                $sql .= " AND workspace_id = ?";
                $params[] = $workspaceId;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existingByPhone[$row['phone']] = $row;
            }
        }

        foreach ($prospects as &$p) {
            $p['is_imported'] = false;
            $p['existing_lead_id'] = null;

            if (!empty($p['place_id']) && isset($existingByPlace[$p['place_id']])) {
                $p['is_imported'] = true;
                $p['existing_lead_id'] = (int)$existingByPlace[$p['place_id']]['id'];
            } elseif (!empty($p['phone']) && isset($existingByPhone[$p['phone']])) {
                $p['is_imported'] = true;
                $p['existing_lead_id'] = (int)$existingByPhone[$p['phone']]['id'];
            }
        }
        unset($p);
    }

    public function qualifyProspects(array &$prospects, array $includeKeywords = [], array $excludeKeywords = [], string $industry = ''): void {
        $includeKeywords = array_filter(array_map('trim', array_map('strtolower', $includeKeywords)));
        $excludeKeywords = array_filter(array_map('trim', array_map('strtolower', $excludeKeywords)));
        $industryLower = strtolower(trim($industry));

        foreach ($prospects as &$p) {
            $haystack = strtolower(($p['name'] ?? '') . ' ' . ($p['company'] ?? '') . ' ' . ($p['category'] ?? '') . ' ' . ($p['address'] ?? ''));
            $matchedInclude = [];
            $matchedExclude = [];

            foreach ($excludeKeywords as $ex) {
                if ($ex !== '' && str_contains($haystack, $ex)) {
                    $matchedExclude[] = $ex;
                }
            }

            foreach ($includeKeywords as $inc) {
                if ($inc !== '' && str_contains($haystack, $inc)) {
                    $matchedInclude[] = $inc;
                }
            }

            $score = (int)($p['score'] ?? 75);

            if (!empty($matchedExclude)) {
                $score = max(15, $score - 45);
                $p['qualification'] = 'Not Relevant';
                $p['is_excluded'] = true;
            } else {
                $p['is_excluded'] = false;
                if (!empty($matchedInclude)) {
                    $score = min(99, $score + (count($matchedInclude) * 8));
                }
                if ($industryLower !== '' && str_contains($haystack, $industryLower)) {
                    $score = min(99, $score + 6);
                }

                if ($score >= 85) {
                    $p['qualification'] = 'Highly Relevant';
                } elseif ($score >= 70) {
                    $p['qualification'] = 'Relevant';
                } elseif ($score >= 50) {
                    $p['qualification'] = 'Potentially Relevant';
                } else {
                    $p['qualification'] = 'Not Relevant';
                }
            }

            $p['score'] = $score;
            $p['matched_keywords'] = $matchedInclude;
            $p['matched_excludes'] = $matchedExclude;
        }
        unset($p);
    }

    public function logDiscovery(PDO $pdo, int $workspaceId, ?int $campaignId, int $userId, string $query, int $count): void {
        try {
            $stmt = $pdo->prepare("INSERT INTO discovery_logs (workspace_id, campaign_id, user_id, search_query, results_count) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$workspaceId, $campaignId, $userId, 'Meta: ' . substr($query, 0, 245), $count]);
        } catch (Throwable $e) {
            error_log("Failed to log Meta discovery: " . $e->getMessage());
        }
    }
}

