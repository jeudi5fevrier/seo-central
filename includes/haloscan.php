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
     * Recupere les stats d'un domaine via 2 appels :
     * - /domains/overview pour le trafic organique total et le nombre de KW
     * - /domains/positions pour le detail des top 10 KW
     */
    public function refreshSite(string $domain): array
    {
        // 1. Overview : stats globales du domaine (trafic organique, nb KW)
        $overview = $this->post('/domains/overview', [
            'input' => $domain,
            'mode' => 'root',
            'requested_data' => ['metrics'],
        ]);

        appLog('API', "Overview brut pour $domain", ['response' => $overview]);

        $totalKwCount = 0;
        $totalTraffic = 0;

        if ($overview && !empty($overview['results'])) {
            $metrics = $overview['results'][0] ?? [];
            $totalKwCount = (int)($metrics['organic_keywords'] ?? $metrics['total_keyword_count'] ?? $metrics['keywords'] ?? 0);
            $totalTraffic = (float)($metrics['organic_traffic'] ?? $metrics['traffic'] ?? 0);
            appLog('INFO', "Overview pour $domain", ['kw_count' => $totalKwCount, 'traffic' => $totalTraffic]);
        }

        // 2. Positions : top 10 KW par trafic (pour la page detail)
        $positions = $this->post('/domains/positions', [
            'input' => $domain,
            'mode' => 'root',
            'lineCount' => 10,
            'order_by' => 'traffic',
            'order' => 'desc',
        ]);

        $keywords = [];
        if ($positions && !empty($positions['results'])) {
            foreach ($positions['results'] as $r) {
                $keywords[] = [
                    'keyword' => $r['keyword'] ?? '',
                    'position' => (int)($r['position'] ?? 0),
                    'volume' => (int)($r['volume'] ?? 0),
                    'traffic' => (float)($r['traffic'] ?? 0),
                ];
            }

            // Fallback : si l'overview n'a pas marche, utiliser les donnees de positions
            if ($totalKwCount === 0) {
                $totalKwCount = (int)($positions['total_keyword_count'] ?? count($keywords));
            }
            if ($totalTraffic === 0) {
                foreach ($keywords as $kw) {
                    $totalTraffic += $kw['traffic'];
                }
            }
        }

        appLog('INFO', "Donnees finales pour $domain", ['kw_count' => $totalKwCount, 'traffic' => $totalTraffic, 'keywords' => count($keywords)]);

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
