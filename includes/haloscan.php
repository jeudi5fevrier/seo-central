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
     * Recupere les top KW d'un domaine via /domains/positions.
     * Retourne les 10 meilleurs KW par trafic + le total de KW du domaine.
     */
    public function refreshSite(string $domain): array
    {
        $response = $this->post('/domains/positions', [
            'input' => $domain,
            'mode' => 'root',
            'lineCount' => 500,
            'order_by' => 'traffic',
            'order' => 'desc',
        ]);

        if (!$response || empty($response['results'])) {
            appLog('INFO', "Aucun resultat /domains/positions pour $domain");
            return [
                'kw_count' => 0,
                'traffic' => 0,
                'keywords' => [],
            ];
        }

        $keywords = [];
        $totalTraffic = 0;
        foreach ($response['results'] as $r) {
            $kw = [
                'keyword' => $r['keyword'] ?? '',
                'position' => (int)($r['position'] ?? 0),
                'volume' => (int)($r['volume'] ?? 0),
                'traffic' => (float)($r['traffic'] ?? 0),
            ];
            $keywords[] = $kw;
            $totalTraffic += $kw['traffic'];
        }

        // total_keyword_count = nombre reel de KW du domaine (pas juste les 10 retournes)
        $totalKwCount = (int)($response['total_keyword_count'] ?? count($keywords));

        appLog('INFO', "Donnees pour $domain", ['total_kw' => $totalKwCount, 'top10_traffic' => $totalTraffic, 'results' => count($keywords)]);

        return [
            'kw_count' => $totalKwCount,
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

        if ($httpCode < 200 || $httpCode >= 300) {
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
