<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use PDO;
use RuntimeException;
use Throwable;

class ServiceOrderController extends Controller
{
    private const MAX_UPLOAD_BYTES = 10485760; // 10 MB

    private const ALLOWED_DOCUMENT_EXTENSIONS = [
        'pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'
    ];

    private const ALLOWED_PAYMENT_EXTENSIONS = [
        'pdf', 'jpg', 'jpeg', 'png', 'webp'
    ];

    private function db(): PDO
    {
        if (function_exists('app')) {
            try {
                $db = app('db');

                if ($db instanceof PDO) {
                    return $db;
                }

                foreach (['pdo', 'getPdo', 'connection', 'getConnection'] as $method) {
                    if (is_object($db) && method_exists($db, $method)) {
                        $pdo = $db->{$method}();

                        if ($pdo instanceof PDO) {
                            return $pdo;
                        }
                    }
                }

                if (is_object($db) && isset($db->pdo) && $db->pdo instanceof PDO) {
                    return $db->pdo;
                }
            } catch (Throwable $e) {
                // Continue fallback checks below.
            }
        }

        $databaseClass = '\\App\\Core\\Database';

        if (class_exists($databaseClass)) {
            foreach (['pdo', 'connection', 'getConnection', 'getInstance'] as $method) {
                try {
                    if (is_callable([$databaseClass, $method])) {
                        $result = $databaseClass::{$method}();

                        if ($result instanceof PDO) {
                            return $result;
                        }

                        if (is_object($result)) {
                            foreach (['pdo', 'getPdo', 'connection', 'getConnection'] as $innerMethod) {
                                if (method_exists($result, $innerMethod)) {
                                    $pdo = $result->{$innerMethod}();

                                    if ($pdo instanceof PDO) {
                                        return $pdo;
                                    }
                                }
                            }

                            if (isset($result->pdo) && $result->pdo instanceof PDO) {
                                return $result->pdo;
                            }
                        }
                    }
                } catch (Throwable $e) {
                    // Continue trying other methods.
                }
            }
        }

        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            return $GLOBALS['pdo'];
        }

        throw new RuntimeException('PDO database connection not found. Update ServiceOrderController::db() for your app DB helper.');
    }

    private function currentClientId(): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $paths = [
            $_SESSION['client']['id'] ?? null,
            $_SESSION['user']['id'] ?? null,
            $_SESSION['auth_user']['id'] ?? null,
            $_SESSION['client_id'] ?? null,
            $_SESSION['user_id'] ?? null,
        ];

        foreach ($paths as $value) {
            $id = (int) $value;

            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }

    private function flashMessage(string $type, string $message): void
    {
        if (function_exists('flash')) {
            flash($type, $message);
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $_SESSION['flash'][$type] = $message;
    }

    private function url(string $path): string
    {
        if (function_exists('base_url')) {
            return base_url($path);
        }

        return '/' . ltrim($path, '/');
    }

    private function go(string $url): void
    {
        if (function_exists('redirect')) {
            redirect($url);
            return;
        }

        header('Location: ' . $url);
        exit;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

private function getService(int $serviceId): ?array
{
    if ($serviceId <= 0) {
        return null;
    }

    $stmt = $this->db()->prepare("
        SELECT 
            id,
            title,
            slug,
            description,
            filing_fee,
            turnaround_days,
            icon,
            is_active
        FROM services
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $serviceId,
    ]);

    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($service)) {
        return null;
    }

    if ((int) ($service['id'] ?? 0) !== $serviceId) {
        return null;
    }

    if (isset($service['is_active']) && (int) $service['is_active'] !== 1) {
        return null;
    }

    if (trim((string) ($service['slug'] ?? '')) === '') {
        return null;
    }

    return $service;
}

    private function getRequirements(int $serviceId): array
    {
        $stmt = $this->db()->prepare("
            SELECT id, service_id, label, help_text, is_required, allow_multiple
            FROM service_requirements
            WHERE service_id = :service_id
            ORDER BY id ASC
        ");

        $stmt->execute([
            ':service_id' => $serviceId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function findClientOrder(int $orderId, int $clientId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                o.*,
                s.title AS service_title,
                s.description AS service_description,
                s.filing_fee AS service_filing_fee,
                s.turnaround_days AS service_turnaround_days,
                s.icon AS service_icon
            FROM orders o
            INNER JOIN services s ON s.id = o.service_id
            WHERE o.id = :order_id
              AND o.client_id = :client_id
            LIMIT 1
        ");

        $stmt->execute([
            ':order_id' => $orderId,
            ':client_id' => $clientId,
        ]);

        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($order) ? $order : null;
    }

    private function generateOrderNo(int $orderId): string
    {
        return 'TSO-' . date('Y') . '-' . str_pad((string) $orderId, 5, '0', STR_PAD_LEFT);
    }

    private function uploadBaseDir(): string
    {
        return dirname(__DIR__, 2) . '/storage/uploads/orders';
    }

    private function ensureUploadDir(int $orderId): string
    {
        $dir = $this->uploadBaseDir() . '/' . $orderId;

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException('Failed to create upload directory.');
            }
        }

        return $dir;
    }

    private function cleanOriginalName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return 'upload';
        }

        return preg_replace('/[^A-Za-z0-9._\- ]+/', '_', $name) ?: 'upload';
    }

    private function extensionFromName(string $name): string
    {
        return strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    }

    private function hasUploadedFile(string $field): bool
    {
        if (!isset($_FILES[$field])) {
            return false;
        }

        $file = $_FILES[$field];

        if (is_array($file['name'] ?? null)) {
            foreach ($file['error'] as $error) {
                if ((int) $error === UPLOAD_ERR_OK) {
                    return true;
                }
            }

            return false;
        }

        return (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    }

    private function normalizeFiles(string $field): array
    {
        if (!isset($_FILES[$field])) {
            return [];
        }

        $file = $_FILES[$field];
        $files = [];

        if (is_array($file['name'] ?? null)) {
            $count = count($file['name']);

            for ($i = 0; $i < $count; $i++) {
                $error = (int) ($file['error'][$i] ?? UPLOAD_ERR_NO_FILE);

                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                $files[] = [
                    'name' => (string) ($file['name'][$i] ?? ''),
                    'type' => (string) ($file['type'][$i] ?? ''),
                    'tmp_name' => (string) ($file['tmp_name'][$i] ?? ''),
                    'error' => $error,
                    'size' => (int) ($file['size'][$i] ?? 0),
                ];
            }

            return $files;
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            return [];
        }

        return [[
            'name' => (string) ($file['name'] ?? ''),
            'type' => (string) ($file['type'] ?? ''),
            'tmp_name' => (string) ($file['tmp_name'] ?? ''),
            'error' => $error,
            'size' => (int) ($file['size'] ?? 0),
        ]];
    }

    private function validateUpload(array $file, array $allowedExtensions): void
    {
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File upload failed. Please re-upload and try again.');
        }

        if ((int) ($file['size'] ?? 0) <= 0) {
            throw new RuntimeException('Uploaded file is empty.');
        }

        if ((int) ($file['size'] ?? 0) > self::MAX_UPLOAD_BYTES) {
            throw new RuntimeException('One uploaded file is larger than 10 MB.');
        }

        $extension = $this->extensionFromName((string) ($file['name'] ?? ''));

        if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException('Invalid file type uploaded.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Invalid uploaded file.');
        }
    }

    private function storePhysicalFile(array $file, int $orderId, array $allowedExtensions): array
    {
        $this->validateUpload($file, $allowedExtensions);

        $uploadDir = $this->ensureUploadDir($orderId);

        $originalName = $this->cleanOriginalName((string) $file['name']);
        $extension = $this->extensionFromName($originalName);

        $storedName = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $targetPath = $uploadDir . '/' . $storedName;

        if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
            throw new RuntimeException('Failed to save uploaded file.');
        }

        $mimeType = null;

        if (function_exists('mime_content_type')) {
            $detectedMime = @mime_content_type($targetPath);

            if (is_string($detectedMime) && $detectedMime !== '') {
                $mimeType = $detectedMime;
            }
        }

        if ($mimeType === null) {
            $mimeType = (string) ($file['type'] ?? '');
        }

        return [
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'size_bytes' => (int) $file['size'],
        ];
    }

    private function insertOrderDocument(
        int $orderId,
        ?int $requirementId,
        string $source,
        string $label,
        array $storedFile,
        int $uploadedBy
    ): void {
        $stmt = $this->db()->prepare("
            INSERT INTO order_documents
                (
                    order_id,
                    requirement_id,
                    source,
                    label,
                    original_name,
                    stored_name,
                    mime_type,
                    size_bytes,
                    is_client_visible,
                    uploaded_by,
                    created_at
                )
            VALUES
                (
                    :order_id,
                    :requirement_id,
                    :source,
                    :label,
                    :original_name,
                    :stored_name,
                    :mime_type,
                    :size_bytes,
                    :is_client_visible,
                    :uploaded_by,
                    :created_at
                )
        ");

        $stmt->execute([
            ':order_id' => $orderId,
            ':requirement_id' => $requirementId,
            ':source' => $source,
            ':label' => $label,
            ':original_name' => $storedFile['original_name'],
            ':stored_name' => $storedFile['stored_name'],
            ':mime_type' => $storedFile['mime_type'],
            ':size_bytes' => $storedFile['size_bytes'],
            ':is_client_visible' => 0,
            ':uploaded_by' => $uploadedBy,
            ':created_at' => $this->now(),
        ]);
    }

    private function saveRequirementUploads(int $orderId, int $clientId, array $requirements): void
    {
        foreach ($requirements as $requirement) {
            $requirementId = (int) ($requirement['id'] ?? 0);

            if ($requirementId <= 0) {
                continue;
            }

            $field = 'requirement_' . $requirementId;
            $label = trim((string) ($requirement['label'] ?? 'Client Document'));

            if ($label === '') {
                $label = 'Client Document';
            }

            $files = $this->normalizeFiles($field);

            foreach ($files as $file) {
                $storedFile = $this->storePhysicalFile(
                    $file,
                    $orderId,
                    self::ALLOWED_DOCUMENT_EXTENSIONS
                );

                $this->insertOrderDocument(
                    $orderId,
                    $requirementId,
                    'client',
                    $label,
                    $storedFile,
                    $clientId
                );
            }
        }
    }

    public function store(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->go($this->url('services'));
            return;
        }

        $clientId = $this->currentClientId();

        if ($clientId <= 0) {
            $this->flashMessage('error', 'Please login before placing an order.');
            $this->go($this->url('auth'));
            return;
        }

        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $financialYear = trim((string) ($_POST['financial_year'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if ($serviceId <= 0) {
            $this->flashMessage('error', 'Invalid service selected.');
            $this->go($_SERVER['HTTP_REFERER'] ?? $this->url('services'));
            return;
        }

        if ($financialYear === '') {
            $this->flashMessage('error', 'Please select financial year.');
            $this->go($_SERVER['HTTP_REFERER'] ?? $this->url('service-order'));
            return;
        }

        $service = $this->getService($serviceId);

        if (!$service) {
            $this->flashMessage('error', 'Service not found.');
            $this->go($this->url('services'));
            return;
        }

        $requirements = $this->getRequirements($serviceId);
        $missingDocuments = [];

        foreach ($requirements as $requirement) {
            $requirementId = (int) ($requirement['id'] ?? 0);
            $isRequired = (int) ($requirement['is_required'] ?? 0) === 1;

            if ($requirementId > 0 && $isRequired) {
                $field = 'requirement_' . $requirementId;

                if (!$this->hasUploadedFile($field)) {
                    $missingDocuments[] = (string) ($requirement['label'] ?? 'Required document');
                }
            }
        }

        if ($missingDocuments !== []) {
            $this->flashMessage('error', 'Please upload required document: ' . implode(', ', $missingDocuments));
            $this->go($_SERVER['HTTP_REFERER'] ?? $this->url('service-order'));
            return;
        }

        $pdo = $this->db();

        try {
            $pdo->beginTransaction();

            $temporaryOrderNo = 'TMP-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
            $now = $this->now();

            $stmt = $pdo->prepare("
                INSERT INTO orders
                    (
                        order_no,
                        client_id,
                        service_id,
                        financial_year,
                        fee_amount,
                        status,
                        payment_method,
                        payment_status,
                        payment_reference,
                        notes,
                        created_at,
                        updated_at
                    )
                VALUES
                    (
                        :order_no,
                        :client_id,
                        :service_id,
                        :financial_year,
                        :fee_amount,
                        :status,
                        :payment_method,
                        :payment_status,
                        :payment_reference,
                        :notes,
                        :created_at,
                        :updated_at
                    )
            ");

            $stmt->execute([
                ':order_no' => $temporaryOrderNo,
                ':client_id' => $clientId,
                ':service_id' => $serviceId,
                ':financial_year' => $financialYear,
                ':fee_amount' => (float) ($service['filing_fee'] ?? 0),
                ':status' => 'submitted',
                ':payment_method' => 'upi',
                ':payment_status' => 'pending',
                ':payment_reference' => null,
                ':notes' => $notes !== '' ? $notes : null,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);

            $orderId = (int) $pdo->lastInsertId();
            $orderNo = $this->generateOrderNo($orderId);

            $updateStmt = $pdo->prepare("
                UPDATE orders
                SET order_no = :order_no,
                    updated_at = :updated_at
                WHERE id = :id
                LIMIT 1
            ");

            $updateStmt->execute([
                ':order_no' => $orderNo,
                ':updated_at' => $now,
                ':id' => $orderId,
            ]);

            $this->saveRequirementUploads($orderId, $clientId, $requirements);

            $pdo->commit();

            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }

            $_SESSION['last_service_order_id'] = $orderId;

            $this->flashMessage('success', 'Order submitted successfully. Please complete your UPI payment.');
            $this->go($this->url('service-order/payment?order_id=' . $orderId));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $this->flashMessage('error', 'Unable to submit order: ' . $e->getMessage());
            $this->go($_SERVER['HTTP_REFERER'] ?? $this->url('service-order'));
        }
    }

    public function payment(): void
    {
        $clientId = $this->currentClientId();

        if ($clientId <= 0) {
            $this->flashMessage('error', 'Please login to continue payment.');
            $this->go($this->url('auth'));
            return;
        }

        $orderId = (int) ($_GET['order_id'] ?? 0);

        if ($orderId <= 0 && session_status() === PHP_SESSION_ACTIVE) {
            $orderId = (int) ($_SESSION['last_service_order_id'] ?? 0);
        }

        if ($orderId <= 0) {
            $this->flashMessage('error', 'Order not found.');
            $this->go($this->url('client/orders'));
            return;
        }

        $order = $this->findClientOrder($orderId, $clientId);

        if (!$order) {
            $this->flashMessage('error', 'Order not found or access denied.');
            $this->go($this->url('client/orders'));
            return;
        }

        $service = [
            'id' => $order['service_id'],
            'title' => $order['service_title'],
            'description' => $order['service_description'],
            'filing_fee' => $order['fee_amount'],
            'turnaround_days' => $order['service_turnaround_days'],
            'icon' => $order['service_icon'],
        ];

        $this->view('public/order-service', [
            'step' => 'payment',
            'order' => $order,
            'service' => $service,
            'requirements' => [],
            'paymentUpiId' => 'yourupi@bank',
            'paymentReceiverName' => 'Tax Saathi',
            'paymentQrImage' => $this->url('assets/images/upi-qr.png'),
        ]);
    }

    public function submitPayment(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->go($this->url('client/orders'));
            return;
        }

        $clientId = $this->currentClientId();

        if ($clientId <= 0) {
            $this->flashMessage('error', 'Please login to submit payment proof.');
            $this->go($this->url('auth'));
            return;
        }

        $orderId = (int) ($_POST['order_id'] ?? 0);
        $transactionId = trim((string) ($_POST['transaction_id'] ?? ''));

        if ($orderId <= 0) {
            $this->flashMessage('error', 'Invalid order.');
            $this->go($this->url('client/orders'));
            return;
        }

        if ($transactionId === '') {
            $this->flashMessage('error', 'Please enter UPI transaction ID.');
            $this->go($this->url('service-order/payment?order_id=' . $orderId));
            return;
        }

        if (!$this->hasUploadedFile('payment_screenshot')) {
            $this->flashMessage('error', 'Please upload payment screenshot.');
            $this->go($this->url('service-order/payment?order_id=' . $orderId));
            return;
        }

        $order = $this->findClientOrder($orderId, $clientId);

        if (!$order) {
            $this->flashMessage('error', 'Order not found or access denied.');
            $this->go($this->url('client/orders'));
            return;
        }

        $pdo = $this->db();

        try {
            $pdo->beginTransaction();

            $files = $this->normalizeFiles('payment_screenshot');
            $paymentFile = $files[0] ?? null;

            if (!$paymentFile) {
                throw new RuntimeException('Payment screenshot file not found.');
            }

            $storedFile = $this->storePhysicalFile(
                $paymentFile,
                $orderId,
                self::ALLOWED_PAYMENT_EXTENSIONS
            );

            $this->insertOrderDocument(
                $orderId,
                null,
                'payment_proof',
                'UPI Payment Screenshot',
                $storedFile,
                $clientId
            );

            $stmt = $pdo->prepare("
                UPDATE orders
                SET payment_method = :payment_method,
                    payment_status = :payment_status,
                    payment_reference = :payment_reference,
                    updated_at = :updated_at
                WHERE id = :id
                  AND client_id = :client_id
                LIMIT 1
            ");

            $stmt->execute([
                ':payment_method' => 'upi',
                ':payment_status' => 'pending_review',
                ':payment_reference' => $transactionId,
                ':updated_at' => $this->now(),
                ':id' => $orderId,
                ':client_id' => $clientId,
            ]);

            $pdo->commit();

            $this->flashMessage('success', 'Payment proof submitted successfully. Your payment is pending admin verification.');
            $this->go($this->url('client/orders/view?id=' . $orderId));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $this->flashMessage('error', 'Unable to submit payment proof: ' . $e->getMessage());
            $this->go($this->url('service-order/payment?order_id=' . $orderId));
        }
    }
}