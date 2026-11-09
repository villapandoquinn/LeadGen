<?php
/**
 * GooglePlacesService
 * Official Google Maps Platform / Places API Integration
 * Supports Google Places API (New) with fallback to legacy Text Search.
 */
class GooglePlacesService {
    private ?string $apiKey = null;
    private string $settingsFile;

    public function __construct(?string $apiKey = null) {
        $this->settingsFile = __DIR__ . '/../config/settings.json';
        if (!empty($apiKey)) {
            $this->apiKey = trim($apiKey);
        } else {
            $this->apiKey = $this->resolveApiKey();
        }
    }

    private function resolveApiKey(): ?string {
        $envKey = getenv('GOOGLE_MAPS_API_KEY') ?: getenv('GOOGLE_PLACES_API_KEY');
        if (!empty($envKey)) return trim($envKey);

        if (file_exists($this->settingsFile)) {
            $json = json_decode(file_get_contents($this->settingsFile), true);
            if (!empty($json['google_maps_api_key'])) {
                return trim($json['google_maps_api_key']);
            }
        }
        return null;
    }

    public function isConfigured(): bool {
        return !empty($this->apiKey);
    }

    public function getApiKey(): ?string {
        return $this->apiKey;
    }

    /**
     * Test connection to Google Places API using provided or stored key
     */
    public function testKey(?string $testKey = null): array {
        $key = !empty($testKey) ? trim($testKey) : $this->apiKey;
        if (empty($key)) {
            return ['success' => false, 'message' => 'No Google Maps / Places API key provided.'];
        }

        // Test with Google Places API (New) searchText
        $url = 'https://places.googleapis.com/v1/places:searchText';
        $payload = json_encode(['textQuery' => 'coffee', 'pageSize' => 1]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Goog-Api-Key: ' . $key,
                'X-Goog-FieldMask: places.id,places.displayName'
            ],
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

        if ($httpCode === 200 && (isset($data['places']) || empty($data['places']))) {
            return ['success' => true, 'message' => 'Google Places API (New) verified successfully!'];
        }

        // Check legacy Places API Text Search as fallback test
        $legacyUrl = 'https://maps.googleapis.com/maps/api/place/textsearch/json?query=coffee&key=' . urlencode($key);
        $legacyResp = @file_get_contents($legacyUrl);
        if ($legacyResp) {
            $legacyData = json_decode($legacyResp, true);
            if (!empty($legacyData['status']) && in_array($legacyData['status'], ['OK', 'ZERO_RESULTS'], true)) {
                return ['success' => true, 'message' => 'Legacy Places API verified successfully!'];
            }
        }

        $errMsg = $data['error']['message'] ?? ($data['error']['status'] ?? 'Google API rejected the request (HTTP ' . $httpCode . ')');
        return ['success' => false, 'message' => 'API Error: ' . $errMsg];
    }
    /**
     * Search real businesses via Google Places API (New) with fallback
     */
    public function searchPlaces(string $query, array $filters = []): array {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'configured' => false,
                'message' => 'Google Maps / Places API key is not configured.',
                'results' => []
            ];
        }

        $minRating = (float)($filters['min_rating'] ?? 0);
        $pageSize = min(20, max(5, (int)($filters['limit'] ?? 20)));
        $pageToken = !empty($filters['page_token']) ? trim($filters['page_token']) : null;

        // Try Google Places API (New) Text Search
        $newApiUrl = 'https://places.googleapis.com/v1/places:searchText';
        $payload = ['textQuery' => $query, 'pageSize' => $pageSize];
        if ($minRating > 0) $payload['minRating'] = $minRating;
        if (!empty($pageToken)) $payload['pageToken'] = $pageToken;

        $fieldMask = [
            'places.id',
            'places.displayName',
            'places.formattedAddress',
            'places.primaryTypeDisplayName',
            'places.types',
            'places.rating',
            'places.userRatingCount',
            'places.nationalPhoneNumber',
            'places.internationalPhoneNumber',
            'places.websiteUri',
            'places.googleMapsUri',
            'places.location',
            'nextPageToken'
        ];

        // Retry mechanism for API rate limits and temporary failures
        $response = null;
        $httpCode = 0;
        $curlErr = null;
        $maxRetries = 2;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $ch = curl_init($newApiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'X-Goog-Api-Key: ' . $this->apiKey,
                    'X-Goog-FieldMask: ' . implode(',', $fieldMask)
                ],
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if (!$curlErr && ($httpCode === 200 || $httpCode === 400 || $httpCode === 403)) {
                break; // Definitive response, no retry
            }

            if ($attempt < $maxRetries) {
                usleep(500000 * ($attempt + 1)); // Backoff: 500ms, 1000ms
            }
        }

        $results = [];

        if (!$curlErr && $httpCode === 200) {
            $data = json_decode($response, true);
            $places = $data['places'] ?? [];
            $nextPageToken = $data['nextPageToken'] ?? null;

            foreach ($places as $p) {
                $compName = $p['displayName']['text'] ?? 'Unknown Business';
                $addr = $p['formattedAddress'] ?? '';
                $rating = isset($p['rating']) ? (float)$p['rating'] : null;
                $reviews = isset($p['userRatingCount']) ? (int)$p['userRatingCount'] : 0;
                $phone = $p['nationalPhoneNumber'] ?? ($p['internationalPhoneNumber'] ?? '');
                $website = $p['websiteUri'] ?? '';
                $mapsUrl = $p['googleMapsUri'] ?? ('https://www.google.com/maps/search/?api=1&query=' . urlencode($compName . ' ' . $addr));
                $category = $p['primaryTypeDisplayName']['text'] ?? '';
                if (empty($category) && !empty($p['types'][0])) {
                    $category = ucwords(str_replace('_', ' ', $p['types'][0]));
                }

                $score = 70;
                if ($rating !== null && $rating >= 4.5) $score += 10;
                elseif ($rating !== null && $rating >= 4.0) $score += 6;
                if (!empty($website)) $score += 8;
                if (!empty($phone)) $score += 6;
                if ($reviews >= 20) $score += 4;
                $score = min(98, $score);

                $results[] = [
                    'place_id' => $p['id'] ?? '',
                    'company' => $compName,
                    'name' => $compName,
                    'category' => $category,
                    'address' => $addr,
                    'location' => $addr,
                    'phone' => $phone,
                    'website' => $website,
                    'rating' => $rating,
                    'reviews_count' => $reviews,
                    'employee_count' => null,
                    'employee_count_source' => null,
                    'google_maps_url' => $mapsUrl,
                    'source' => 'google',
                    'score' => $score,
                    'qualification' => 'Relevant'
                ];
            }

            return [
                'success' => true,
                'configured' => true,
                'count' => count($results),
                'next_page_token' => $nextPageToken,
                'results' => $results
            ];
        }

        $errorMsg = 'Failed to fetch places from Google Maps API.';
        if (!empty($response)) {
            $d = json_decode($response, true);
            if (!empty($d['error']['message'])) $errorMsg = $d['error']['message'];
        }

        return ['success' => false, 'configured' => true, 'message' => $errorMsg, 'results' => []];
    }

    /**
     * Check database for duplicate leads by place_id or phone within active workspace
     */
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

    /**
     * Intelligent Keyword Filtering & Qualification Engine
     * Evaluates include/exclude keywords, categories, and business data
     */
    public function qualifyProspects(array &$prospects, array $includeKeywords = [], array $excludeKeywords = [], string $industry = ''): void {
        $includeKeywords = array_filter(array_map('trim', array_map('strtolower', $includeKeywords)));
        $excludeKeywords = array_filter(array_map('trim', array_map('strtolower', $excludeKeywords)));
        $industryLower = strtolower(trim($industry));

        foreach ($prospects as &$p) {
            $haystack = strtolower(($p['name'] ?? '') . ' ' . ($p['company'] ?? '') . ' ' . ($p['category'] ?? '') . ' ' . ($p['address'] ?? ''));
            $matchedInclude = [];
            $matchedExclude = [];

            // Check exclusions
            foreach ($excludeKeywords as $ex) {
                if ($ex !== '' && str_contains($haystack, $ex)) {
                    $matchedExclude[] = $ex;
                }
            }

            // Check inclusions
            foreach ($includeKeywords as $inc) {
                if ($inc !== '' && str_contains($haystack, $inc)) {
                    $matchedInclude[] = $inc;
                }
            }

            $score = (int)($p['score'] ?? 70);

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

    /**
     * Log discovery queries to discovery_logs table
     */
    public function logDiscovery(PDO $pdo, int $workspaceId, ?int $campaignId, int $userId, string $query, int $count): void {
        try {
            $stmt = $pdo->prepare("INSERT INTO discovery_logs (workspace_id, campaign_id, user_id, search_query, results_count) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$workspaceId, $campaignId, $userId, substr($query, 0, 255), $count]);
        } catch (Throwable $e) {
            error_log("Failed to log discovery: " . $e->getMessage());
        }
    }
}
