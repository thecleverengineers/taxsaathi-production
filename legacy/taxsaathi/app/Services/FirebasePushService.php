<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Throwable;

final class FirebasePushService
{
    private array $config;
    private array $serviceConfig;
    private array $webConfig;

    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->serviceConfig = (array) ($this->config['server'] ?? []);
        $this->webConfig = (array) ($this->config['web'] ?? []);
    }

    public function isConfigured(): bool
    {
        $projectId = trim((string) ($this->serviceConfig['project_id'] ?? ''));
        $serviceAccountPath = trim((string) ($this->serviceConfig['service_account_path'] ?? ''));

        return $projectId !== '' && $serviceAccountPath !== '' && is_file($serviceAccountPath);
    }

    public function saveUserToken(int $userId, string $token, string $deviceKey, string $userAgent = ''): void
    {
        $userId = (int) $userId;
        $token = trim($token);
        $deviceKey = trim($deviceKey);

        if ($userId <= 0 || $token === '' || $deviceKey === '') {
            throw new RuntimeException('Invalid push token payload.');
        }

        $now = date('Y-m-d H:i:s');

        $sql = "
            INSERT INTO user_push_tokens
                (user_id, fcm_token, device_key, platform, user_agent, is_active, last_seen_at, created_at, updated_at)
            VALUES
                (:user_id, :fcm_token, :device_key, 'web', :user_agent, 1, :last_seen_at, :created_at, :updated_at)
            ON DUPLICATE KEY UPDATE
                fcm_token = VALUES(fcm_token),
                user_agent = VALUES(user_agent),
                is_active = 1,
                last_seen_at = VALUES(last_seen_at),
                updated_at = VALUES(updated_at)
        ";

        $this->dbWrite($sql, [
            'user_id'      => $userId,
            'fcm_token'    => $token,
            'device_key'   => substr($deviceKey, 0, 100),
            'user_agent'   => substr($userAgent, 0, 255),
            'last_seen_at' => $now,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
    }

    public function deactivateToken(string $token = '', string $deviceKey = '', int $userId = 0): void
    {
        $token = trim($token);
        $deviceKey = trim($deviceKey);
        $userId = (int) $userId;

        if ($token === '' && $deviceKey === '') {
            return;
        }

        $conditions = [];
        $params = ['updated_at' => date('Y-m-d H:i:s')];

        if ($token !== '') {
            $conditions[] = 'fcm_token = :fcm_token';
            $params['fcm_token'] = $token;
        }

        if ($deviceKey !== '') {
            $conditions[] = 'device_key = :device_key';
            $params['device_key'] = $deviceKey;
        }

        if ($userId > 0) {
            $conditions[] = 'user_id = :user_id';
            $params['user_id'] = $userId;
        }

        if ($conditions === []) {
            return;
        }

        $sql = "
            UPDATE user_push_tokens
               SET is_active = 0,
                   updated_at = :updated_at
             WHERE " . implode(' AND ', $conditions);

        $this->dbWrite($sql, $params);
    }

    public function sendToUser(int $userId, array $item): array
    {
        return $this->sendToUsers([$userId], $item);
    }

    public function sendToUsers(array $userIds, array $item): array
    {
        $userIds = array_values(array_unique(array_filter(array_map(static fn ($id): int => (int) $id, $userIds))));
        if ($userIds === [] || !$this->isConfigured()) {
            return [];
        }

        $tokens = $this->getActiveTokensForUsers($userIds);
        if ($tokens === []) {
            return [];
        }

        $title = trim((string) ($item['title'] ?? 'New notification'));
        $body = trim((string) ($item['message'] ?? ''));
        $uid = trim((string) ($item['uid'] ?? ''));
        $url = $this->normalizeOpenUrl((string) ($item['url'] ?? ''));
        $openAction = function_exists('base_url') ? base_url('notifications/open') : '/notifications/open';
        $defaults = (array) ($this->config['defaults'] ?? []);

        $results = [];

        foreach ($tokens as $row) {
            $result = $this->sendToToken((string) $row['fcm_token'], [
                'title' => $title,
                'body' => $body,
                'uid' => $uid,
                'kind' => (string) ($item['kind'] ?? ''),
                'status_label' => (string) ($item['status_label'] ?? ''),
                'url' => $url,
                'open_action' => $openAction,
                'icon' => (string) ($item['icon'] ?? ($defaults['icon'] ?? '')),
                'badge' => (string) ($item['badge'] ?? ($defaults['badge'] ?? '')),
                'tag' => (string) ($item['tag'] ?? $uid),
                'ttl' => (string) ($defaults['ttl'] ?? '120'),
            ]);

            $results[] = [
                'user_id' => (int) ($row['user_id'] ?? 0),
                'token' => (string) $row['fcm_token'],
                'ok' => $result['ok'],
                'status' => $result['status'],
                'response' => $result['body'],
            ];

            if (!$result['ok'] && $this->responseMeansTokenIsDead($result['status'], $result['body'])) {
                $this->deactivateToken((string) $row['fcm_token']);
            }
        }

        return $results;
    }

    private function sendToToken(string $token, array $payload): array
    {
        $projectId = trim((string) ($this->serviceConfig['project_id'] ?? ''));
        $endpoint = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode($projectId) . '/messages:send';

        $body = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => (string) ($payload['title'] ?? 'New notification'),
                    'body' => (string) ($payload['body'] ?? ''),
                ],
                'data' => $this->stringifyData([
                    'notification_uid' => (string) ($payload['uid'] ?? ''),
                    'uid'              => (string) ($payload['uid'] ?? ''),
                    'title'            => (string) ($payload['title'] ?? ''),
                    'body'             => (string) ($payload['body'] ?? ''),
                    'kind'             => (string) ($payload['kind'] ?? ''),
                    'status_label'     => (string) ($payload['status_label'] ?? ''),
                    'url'              => (string) ($payload['url'] ?? ''),
                    'open_action'      => (string) ($payload['open_action'] ?? ''),
                    'tag'              => (string) ($payload['tag'] ?? ''),
                    'icon'             => (string) ($payload['icon'] ?? ''),
                    'badge'            => (string) ($payload['badge'] ?? ''),
                ]),
                'webpush' => [
                    'headers' => [
                        'TTL' => (string) ($payload['ttl'] ?? '120'),
                        'Urgency' => 'high',
                    ],
                    'notification' => [
                        'title' => (string) ($payload['title'] ?? 'New notification'),
                        'body'  => (string) ($payload['body'] ?? ''),
                        'icon'  => (string) ($payload['icon'] ?? ''),
                        'badge' => (string) ($payload['badge'] ?? ''),
                        'tag'   => (string) ($payload['tag'] ?? ''),
                        'data'  => [
                            'uid' => (string) ($payload['uid'] ?? ''),
                            'url' => (string) ($payload['url'] ?? ''),
                            'open_url' => (string) ($payload['open_action'] ?? ''),
                        ],
                    ],
                    'fcm_options' => [
                        'link' => (string) ($payload['url'] ?? ''),
                    ],
                ],
            ],
        ];

        [$status, $responseBody] = $this->curlJson(
            'POST',
            $endpoint,
            json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            [
                'Authorization: Bearer ' . $this->getAccessToken(),
                'Content-Type: application/json; charset=utf-8',
            ]
        );

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => $responseBody,
        ];
    }

    private function getActiveTokensForUsers(array $userIds): array
    {
        $db = app('db');
        $params = [];
        $placeholders = [];

        foreach ($userIds as $index => $userId) {
            $key = 'user_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int) $userId;
        }

        if ($placeholders === []) {
            return [];
        }

        return $db->fetchAll(
            "SELECT user_id, fcm_token
               FROM user_push_tokens
              WHERE is_active = 1
                AND user_id IN (" . implode(', ', $placeholders) . ")",
            $params
        ) ?: [];
    }

    private function getAccessToken(): string
    {
        static $cachedToken = null;
        static $expiresAt = 0;

        if (is_string($cachedToken) && $cachedToken !== '' && $expiresAt > (time() + 60)) {
            return $cachedToken;
        }

        $serviceAccount = $this->readServiceAccount();
        $tokenUri = trim((string) ($serviceAccount['token_uri'] ?? ($this->serviceConfig['token_uri'] ?? 'https://oauth2.googleapis.com/token')));
        $jwt = $this->buildJwtAssertion($serviceAccount, $tokenUri);

        [$status, $responseBody] = $this->curlForm(
            $tokenUri,
            http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ], '', '&', PHP_QUERY_RFC3986),
            ['Content-Type: application/x-www-form-urlencoded']
        );

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Could not obtain Firebase access token. ' . $responseBody);
        }

        $decoded = json_decode($responseBody, true);
        $cachedToken = (string) ($decoded['access_token'] ?? '');
        $expiresIn = (int) ($decoded['expires_in'] ?? 3600);
        $expiresAt = time() + max(300, $expiresIn - 120);

        if ($cachedToken === '') {
            throw new RuntimeException('Firebase access token missing.');
        }

        return $cachedToken;
    }

    private function buildJwtAssertion(array $serviceAccount, string $tokenUri): string
    {
        $clientEmail = trim((string) ($serviceAccount['client_email'] ?? ''));
        $privateKey = (string) ($serviceAccount['private_key'] ?? '');

        if ($clientEmail === '' || $privateKey === '') {
            throw new RuntimeException('Firebase service account JSON is missing client_email or private_key.');
        }

        $now = time();

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss'   => $clientEmail,
            'sub'   => $clientEmail,
            'aud'   => $tokenUri,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];

        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES)),
            $this->base64UrlEncode(json_encode($claims, JSON_UNESCAPED_SLASHES)),
        ];

        $signingInput = implode('.', $segments);
        $signature = '';

        $ok = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (!$ok) {
            throw new RuntimeException('Could not sign Firebase JWT assertion.');
        }

        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function readServiceAccount(): array
    {
        $path = trim((string) ($this->serviceConfig['service_account_path'] ?? ''));
        if ($path === '' || !is_file($path)) {
            throw new RuntimeException('Firebase service account file not found.');
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Firebase service account JSON is invalid.');
        }

        return $decoded;
    }

    private function responseMeansTokenIsDead(int $status, string $body): bool
    {
        if ($status === 404 || $status === 410) {
            return true;
        }

        $bodyUpper = strtoupper($body);

        return str_contains($bodyUpper, 'UNREGISTERED')
            || str_contains($bodyUpper, 'INVALID_ARGUMENT')
            || str_contains($bodyUpper, 'REGISTRATION TOKEN IS NOT A VALID FCM REGISTRATION TOKEN');
    }

    private function normalizeOpenUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return function_exists('base_url') ? base_url('dashboard') : '/dashboard';
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return function_exists('base_url')
            ? base_url(ltrim($url, '/'))
            : '/' . ltrim($url, '/');
    }

    private function stringifyData(array $data): array
    {
        $output = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }
            $output[(string) $key] = is_scalar($value) ? (string) $value : json_encode($value);
        }
        return $output;
    }

    private function loadConfig(): array
    {
        try {
            if (function_exists('config')) {
                $config = config('firebase');
                if (is_array($config)) {
                    return $config;
                }
            }
        } catch (Throwable $e) {
        }

        if (defined('BASE_PATH')) {
            $path = BASE_PATH . '/config/firebase.php';
            if (is_file($path)) {
                $config = require $path;
                if (is_array($config)) {
                    return $config;
                }
            }
        }

        return [];
    }

    private function curlForm(string $url, string $body, array $headers = []): array
    {
        return $this->curlRequest('POST', $url, $body, $headers);
    }

    private function curlJson(string $method, string $url, string $body, array $headers = []): array
    {
        return $this->curlRequest($method, $url, $body, $headers);
    }

    private function curlRequest(string $method, string $url, string $body = '', array $headers = []): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Firebase cURL request failed: ' . $error);
        }

        return [$status, (string) $response];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function dbWrite(string $sql, array $params = []): void
    {
        $db = app('db');

        if (method_exists($db, 'execute')) {
            $db->execute($sql, $params);
            return;
        }

        if (method_exists($db, 'statement')) {
            $db->statement($sql, $params);
            return;
        }

        if (method_exists($db, 'query')) {
            $db->query($sql, $params);
            return;
        }

        if (method_exists($db, 'pdo')) {
            $stmt = $db->pdo()->prepare($sql);
            $stmt->execute($params);
            return;
        }

        if (property_exists($db, 'pdo') && $db->pdo instanceof \PDO) {
            $stmt = $db->pdo->prepare($sql);
            $stmt->execute($params);
            return;
        }

        throw new RuntimeException('Database write method not supported.');
    }
}
