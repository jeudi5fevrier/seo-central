<?php
require_once __DIR__ . '/../config.php';

class Haloscan
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->apiKey = HALOSCAN_API_KEY;
        $this->baseUrl = HALOSCAN_BASE_URL;
        $this->timeout = 30;
    }

    /**
     * Etape 1 : Recupere les noms des top KW d'un domaine via pageBestKeywords.
     * Retourne un array de strings (noms de KW).
     */
    public function getTopKeywordNames(string $domain): array
    {
        $response = $this->post('/domains/pageBestKeywords', [
            'input' => [$domain],
            'lineCount' => 10,
            'strategy' => 'only_active',
        ]);

        if (!$response || empty($response['results'])) {
            return [];
        }

        $bestKw = $response['results'][0]['best_keywords'] ?? '';
        if (empty($bestKw)) {
            return [];
        }

        // Parse la string CSV : "kw1, kw2, kw3"
        $keywords = array_map('trim', explode(',', $bestKw));
        return array_filter($keywords, fn($kw) => $kw !== '');
    }

    /**
     * Etape 2 : Recupere les donnees detaillees pour des KW specifiques sur un domaine.
     * Retourne un array de ['keyword', 'position', 'volume', 'traffic'].
     */
    public function getKeywordDetails(string $domain, array $keywords): array
    {
        if (empty($keywords)) {
            return [];
        }

        $response = $this->post('/domains/keywords', [
            'input' => $domain,
            'keywords' => $keywords,
            'lineCount' => 10,
            'order' => 'desc',
            'order_by' => 'traffic',
            'mode' => 'root',
        ]);

        if (!$response || empty($response['results'])) {
            return [];
        }

        $results = [];
        foreach ($response['results'] as $r) {
            $results[] = [
                'keyword' => $r['keyword'] ?? '',
                'position' => (int)($r['position'] ?? 0),
                'volume' => (int)($r['volume'] ?? 0),
                'traffic' => (float)($r['traffic'] ?? 0),
            ];
        }

        return $results;
    }

    /**
     * Enchaine les 2 etapes : decouvre les top KW puis recupere les details.
     * Retourne ['kw_count' => int, 'traffic' => float, 'keywords' => array].
     */
    public function refreshSite(string $domain): array
    {
        $kwNames = $this->getTopKeywordNames($domain);

        if (empty($kwNames)) {
            return [
                'kw_count' => 0,
                'traffic' => 0,
                'keywords' => [],
            ];
        }

        $keywords = $this->getKeywordDetails($domain, $kwNames);

        $totalTraffic = 0;
        foreach ($keywords as $kw) {
            $totalTraffic += $kw['traffic'];
        }

        return [
            'kw_count' => count($keywords),
            'traffic' => $totalTraffic,
            'keywords' => $keywords,
        ];
    }

    /**
     * Appel POST generique vers l'API Haloscan.
     */
    private function post(string $endpoint, array $data): ?array
    {
        $url = $this->baseUrl . $endpoint;
        $payload = json_encode($data);

        appLog('API', "Requete $endpoint", ['url' => $url, 'body' => $data]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'haloscan-api-key: ' . $this->apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            appLog('ERROR', "cURL error sur $endpoint", ['curl_error' => $error, 'http_code' => $httpCode]);
            return null;
        }

        if ($httpCode !== 200) {
            appLog('ERROR', "HTTP $httpCode sur $endpoint", ['response' => mb_substr($response, 0, 500)]);
            return null;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            appLog('ERROR', "JSON decode error sur $endpoint", ['json_error' => json_last_error_msg(), 'raw' => mb_substr($response, 0, 500)]);
            return null;
        }

        appLog('API', "Reponse $endpoint OK", ['result_count' => count($decoded['results'] ?? [])]);

        return $decoded;
    }
}
