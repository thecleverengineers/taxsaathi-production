<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

class RazorpayController extends Controller
{
    /**
     * POST /razorpay/create-order
     */
    public function createOrder(): void
    {
        header('Content-Type: application/json');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => 'Method not allowed.',
            ]);
            return;
        }

        $config = $this->razorpayConfig();

        $keyId = trim((string) ($config['key_id'] ?? ''));
        $keySecret = trim((string) ($config['key_secret'] ?? ''));

        if ($keyId === '' || $keySecret === '') {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Razorpay credentials are missing. Check .env, app/config/razorpay.php, and PHP-FPM environment loading.',
            ]);
            return;
        }

        $amount = (int) ($_POST['amount'] ?? 0);
        $currency = strtoupper(trim((string) ($_POST['currency'] ?? ($config['currency'] ?? 'INR'))));
        $applicationId = trim((string) ($_POST['application_id'] ?? ''));
        $serviceTitle = trim((string) ($_POST['service_title'] ?? 'Service Payment'));

        if ($amount <= 0) {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => 'Invalid payment amount.',
            ]);
            return;
        }

        if ($currency === '') {
            $currency = 'INR';
        }

        $receipt = 'app_' . ($applicationId !== '' ? $applicationId : 'payment') . '_' . time();

        $payload = [
            'amount' => $amount,
            'currency' => $currency,
            'receipt' => substr($receipt, 0, 40),
            'payment_capture' => 1,
            'notes' => [
                'application_id' => $applicationId,
                'service_title' => $serviceTitle,
            ],
        ];

        $ch = curl_init('https://api.razorpay.com/v1/orders');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => $keyId . ':' . $keySecret,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30,
        ]);

        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $curlError !== '') {
            http_response_code(502);
            echo json_encode([
                'ok' => false,
                'message' => 'Could not connect to Razorpay.',
                'error' => $curlError,
            ]);
            return;
        }

        $response = json_decode((string) $raw, true);

        if ($statusCode < 200 || $statusCode >= 300 || !is_array($response) || empty($response['id'])) {
            http_response_code($statusCode >= 400 ? $statusCode : 502);
            echo json_encode([
                'ok' => false,
                'message' => $response['error']['description'] ?? 'Razorpay order creation failed.',
                'razorpay_response' => $response,
            ]);
            return;
        }

        echo json_encode([
            'ok' => true,
            'data' => [
                'order_id' => (string) $response['id'],
                'amount' => (int) ($response['amount'] ?? $amount),
                'currency' => (string) ($response['currency'] ?? $currency),
                'key_id' => $keyId,
            ],
        ]);
    }

    private function razorpayConfig(): array
    {
        $env = $this->loadDotEnv();

        $config = [];

        if (function_exists('config')) {
            try {
                $tmp = config('razorpay');
                if (is_array($tmp)) {
                    $config = $tmp;
                }
            } catch (\Throwable $e) {
            }
        }

        if ($config === [] && defined('BASE_PATH') && is_file(BASE_PATH . '/config/razorpay.php')) {
            $tmp = require BASE_PATH . '/config/razorpay.php';
            if (is_array($tmp)) {
                $config = $tmp;
            }
        }

        $defaults = [
            'key_id' => $this->envValue($env, ['RAZORPAY_KEY_ID', 'RAZORPAY_KEY'], ''),
            'key_secret' => $this->envValue($env, ['RAZORPAY_KEY_SECRET', 'RAZORPAY_SECRET'], ''),
            'currency' => $this->envValue($env, ['RAZORPAY_CURRENCY'], 'INR'),
        ];

        foreach ($config as $key => $value) {
            if (trim((string) $value) !== '') {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }

    private function loadDotEnv(): array
    {
        $paths = [];

        if (defined('BASE_PATH')) {
            $basePath = rtrim((string) BASE_PATH, '/');
            $paths[] = $basePath . '/.env';
            $paths[] = dirname($basePath) . '/.env';
            $paths[] = $basePath . '/app/.env';
            $paths[] = $basePath . '/config/.env';
        }

        $paths[] = dirname(__DIR__, 2) . '/.env';
        $paths[] = dirname(__DIR__, 3) . '/.env';
        $paths[] = dirname(__DIR__) . '/.env';

        $values = [];

        foreach (array_unique($paths) as $path) {
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }

            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $line) {
                $line = trim($line);

                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }

                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                if ($key === '') {
                    continue;
                }

                if (
                    (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))
                ) {
                    $value = substr($value, 1, -1);
                }

                $values[$key] = $value;

                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }

            if ($values !== []) {
                break;
            }
        }

        return $values;
    }

    private function envValue(array $env, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $env) && trim((string) $env[$key]) !== '') {
                return trim((string) $env[$key]);
            }

            if (array_key_exists($key, $_ENV) && trim((string) $_ENV[$key]) !== '') {
                return trim((string) $_ENV[$key]);
            }

            if (array_key_exists($key, $_SERVER) && trim((string) $_SERVER[$key]) !== '') {
                return trim((string) $_SERVER[$key]);
            }

            if (function_exists('getenv')) {
                $value = \getenv($key);

                if ($value !== false && trim((string) $value) !== '') {
                    return trim((string) $value);
                }
            }
        }

        return $default;
    }
}