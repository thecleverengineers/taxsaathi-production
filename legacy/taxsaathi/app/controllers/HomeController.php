<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Faq;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\Service;
use App\Models\ServiceRequirement;
use App\Models\TaxRule;
use App\Models\Testimonial;
use PDO;

final class HomeController extends Controller
{
public function index(): void
{
    $this->view('public/home', [
        'title' => 'Tax Saathi – Smart Income Tax Filing & Compliance',
        'categories' => $this->fetchActiveServiceCategories(),
        'services' => $this->fetchActiveServices(null, true),
        'testimonials' => Testimonial::active(),
        'faqs' => Faq::active(),
    ]);
}

public function services(): void
{
    $this->view('public/services', [
        'title' => 'Services – Tax Saathi',
        'categories' => $this->fetchActiveServiceCategories(),
        'services' => $this->fetchActiveServices(),
    ]);
}

public function serviceCategory(): void
{
    $identifier = trim((string) input('category'));

    if ($identifier === '') {
        flash('error', 'Category not found.');
        redirect('services');
        return;
    }

    $category = $this->findActiveServiceCategory($identifier);

    if (!$category) {
        flash('error', 'Requested category was not found.');
        redirect('services');
        return;
    }

    $services = $this->fetchActiveServices((int) $category['id']);

    $this->view('public/service-category', [
        'title' => ((string) $category['title']) . ' – Services',
        'category' => $category,
        'services' => $services,
    ]);
}

private function fetchActiveServiceCategories(): array
{
    $pdo = $this->dbPdo();

    $stmt = $pdo->query(
        "SELECT
            c.id,
            c.title,
            c.slug,
            c.image,
            c.description,
            c.sort_order,
            COUNT(s.id) AS service_count
         FROM service_categories c
         LEFT JOIN services s
           ON s.service_category_id = c.id
          AND s.is_active = 1
         WHERE c.is_active = 1
         GROUP BY c.id, c.title, c.slug, c.image, c.description, c.sort_order
         ORDER BY c.sort_order ASC, c.title ASC"
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

private function findActiveServiceCategory(string $identifier): ?array
{
    $pdo = $this->dbPdo();

    if (ctype_digit($identifier)) {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM service_categories
             WHERE id = :id
               AND is_active = 1
             LIMIT 1"
        );
        $stmt->execute(['id' => (int) $identifier]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM service_categories
             WHERE slug = :slug
               AND is_active = 1
             LIMIT 1"
        );
        $stmt->execute(['slug' => $identifier]);
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

private function fetchActiveServices(?int $categoryId = null, bool $featuredOnly = false): array
{
    $pdo = $this->dbPdo();

    $sql = "
        SELECT
            s.*,
            c.id   AS service_category_id,
            c.title AS service_category_title,
            c.slug AS service_category_slug,
            c.image AS service_category_image,
            c.description AS service_category_description
        FROM services s
        LEFT JOIN service_categories c
          ON c.id = s.service_category_id
        WHERE s.is_active = 1
    ";

    $params = [];

    if ($featuredOnly) {
        $sql .= " AND s.is_featured = 1";
    }

    if ($categoryId !== null) {
        $sql .= " AND s.service_category_id = :category_id";
        $params['category_id'] = $categoryId;
    }

    $sql .= " ORDER BY COALESCE(c.sort_order, 999999) ASC, s.sort_order ASC, s.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$row) {
        $row['service_category_label'] = trim((string) ($row['service_category_title'] ?? 'General Services'));
        $row['service_category_key'] = trim((string) ($row['service_category_slug'] ?? 'general-services'));

        if ($row['service_category_label'] === '') {
            $row['service_category_label'] = 'General Services';
        }

        if ($row['service_category_key'] === '') {
            $row['service_category_key'] = 'general-services';
        }
    }
    unset($row);

    return $rows;
}

private function dbPdo(): \PDO
{
    $db = app('db');

    if ($db instanceof \PDO) {
        return $db;
    }

    if (is_object($db)) {
        if (method_exists($db, 'pdo')) {
            $pdo = $db->pdo();
            if ($pdo instanceof \PDO) {
                return $pdo;
            }
        }

        if (method_exists($db, 'getPdo')) {
            $pdo = $db->getPdo();
            if ($pdo instanceof \PDO) {
                return $pdo;
            }
        }

        if (method_exists($db, 'connection')) {
            $pdo = $db->connection();
            if ($pdo instanceof \PDO) {
                return $pdo;
            }
        }

        if (method_exists($db, 'getConnection')) {
            $pdo = $db->getConnection();
            if ($pdo instanceof \PDO) {
                return $pdo;
            }
        }

        if (property_exists($db, 'pdo') && $db->pdo instanceof \PDO) {
            return $db->pdo;
        }
    }

    throw new \RuntimeException('Database wrapper does not expose a PDO connection.');
}

public function orderForm(): void
{
    $serviceId = (int) ($_GET['service_id'] ?? $_GET['id'] ?? 0);

    if ($serviceId <= 0) {
        flash('error', 'Please select a service first.');
        redirect(base_url('services'));
        return;
    }

    $pdo = app('db');

    $serviceStmt = $pdo->prepare("
        SELECT id, title, description, filing_fee, turnaround_days, icon
        FROM services
        WHERE id = :id
        LIMIT 1
    ");

    $serviceStmt->execute([
        ':id' => $serviceId,
    ]);

    $service = $serviceStmt->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
        flash('error', 'Service not found.');
        redirect(base_url('services'));
        return;
    }

    $requirementsStmt = $pdo->prepare("
        SELECT id, service_id, label, help_text, is_required, allow_multiple
        FROM service_requirements
        WHERE service_id = :service_id
        ORDER BY id ASC
    ");

    $requirementsStmt->execute([
        ':service_id' => $serviceId,
    ]);

    $requirements = $requirementsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $this->view('public/order-service', [
        'step' => 'order',
        'service' => $service,
        'requirements' => $requirements,
    ]);
}

    public function submitOrder(): void
    {
        require_client();
        verify_csrf();

        $serviceId = (int) input('service_id', 0);
        $service = Service::find($serviceId);

        if (!$service) {
            flash('error', 'Service not found.');
            redirect('services');
        }

        $requirements = ServiceRequirement::forService($serviceId);
        $missingDocuments = $this->missingRequiredServiceOrderDocuments($requirements);

        if ($missingDocuments !== []) {
            flash('error', 'Please upload required document(s): ' . implode(', ', $missingDocuments));
            redirect('service-order?service=' . urlencode((string) ($service['slug'] ?? $service['id'])));
        }

        $financialYear = $this->normalizeOrderFinancialYear(
            (string) input('financial_year', $this->serviceOrderCurrentFinancialYear())
        );
        $notes = trim((string) input('notes'));

        /*
         * FINAL FIX:
         * Completely skip payment reference / transaction ID while placing order.
         *
         * Do not read:
         * - payment_method
         * - payment_reference
         * - payment_proof
         * - razorpay_payment_id
         * - razorpay_order_id
         * - razorpay_signature
         *
         * Payment will be collected only after order creation on:
         * /payment-method?order_id=ORDER_ID&source=orders
         */
        $paymentMethod = $this->columnAllowsNull('orders', 'payment_method') ? null : '';
        $paymentReference = $this->columnAllowsNull('orders', 'payment_reference') ? null : '';

        $user = auth_user();
        $db = app('db');

        $clientId = (int) ($user['client_id'] ?? 0);
        $client = null;

        if ($clientId > 0) {
            $client = $db->fetch(
                "SELECT id, name, email, phone
                 FROM clients
                 WHERE id = :id
                 LIMIT 1",
                ['id' => $clientId]
            );
        }

        /*
         * Auto-repair broken users.client_id by matching email / phone.
         */
        if (!$client) {
            $userEmail = trim((string) ($user['email'] ?? ''));
            $userPhone = trim((string) ($user['phone'] ?? $user['mobile'] ?? ''));

            if ($userEmail !== '') {
                $client = $db->fetch(
                    "SELECT id, name, email, phone
                     FROM clients
                     WHERE email = :email
                     LIMIT 1",
                    ['email' => $userEmail]
                );
            }

            if (!$client && $userPhone !== '') {
                $client = $db->fetch(
                    "SELECT id, name, email, phone
                     FROM clients
                     WHERE phone = :phone
                     LIMIT 1",
                    ['phone' => $userPhone]
                );
            }

            if ($client) {
                $clientId = (int) $client['id'];

                $db->execute(
                    "UPDATE users
                     SET client_id = :client_id, updated_at = :updated_at
                     WHERE id = :user_id",
                    [
                        'client_id'  => $clientId,
                        'updated_at' => date('Y-m-d H:i:s'),
                        'user_id'    => (int) ($user['id'] ?? 0),
                    ]
                );
            }
        }

        if (!$client || $clientId <= 0) {
            flash('error', 'Your client account is not linked properly. Please contact support.');
            redirect('client/orders');
        }

        $orderId = $db->transaction(function () use (
            $clientId,
            $service,
            $financialYear,
            $notes,
            $paymentMethod,
            $paymentReference,
            $requirements,
            $user
        ) {
            $orderId = Order::insert([
                'order_no' => Order::nextOrderNo(),
                'client_id' => $clientId,
                'service_id' => (int) $service['id'],
                'financial_year' => $financialYear,
                'fee_amount' => (float) ($service['filing_fee'] ?? 0),
                'status' => 'pending_review',
                'payment_method' => $paymentMethod,
                'payment_status' => 'waiting_for_payment',
                'payment_reference' => $paymentReference,
                'notes' => $notes !== '' ? $notes : null,
                'admin_notes' => null,
                'completion_note' => null,
                'approved_by' => null,
                'approved_at' => null,
                'completed_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            foreach ($requirements as $requirement) {
                $requirementId = (int) ($requirement['id'] ?? 0);

                if ($requirementId <= 0) {
                    continue;
                }

                $inputName = 'requirement_' . $requirementId;

                if (!isset($_FILES[$inputName])) {
                    continue;
                }

                $files = $this->normalizeServiceOrderUploadFiles($_FILES[$inputName]);
                $allowMultiple = (int) ($requirement['allow_multiple'] ?? 0) === 1;
                $uploadedForRequirement = 0;

                foreach ($files as $file) {
                    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }

                    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        continue;
                    }

                    $meta = store_single_upload($file);

                    if (!$meta) {
                        continue;
                    }

                    OrderDocument::insert([
                        'order_id' => $orderId,
                        'requirement_id' => $requirementId,
                        'source' => 'client',
                        'label' => (string) ($requirement['label'] ?? 'Document'),
                        'original_name' => $meta['original_name'],
                        'stored_name' => $meta['stored_name'],
                        'mime_type' => $meta['mime_type'],
                        'size_bytes' => $meta['size_bytes'],
                        'is_client_visible' => 0,
                        'uploaded_by' => (int) ($user['id'] ?? 0),
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);

                    $uploadedForRequirement++;

                    if (!$allowMultiple && $uploadedForRequirement >= 1) {
                        break;
                    }
                }
            }

            /*
             * Keep invoice auto-generation so existing client/admin invoice features
             * continue to work. Invoice remains unpaid until payment is completed.
             */
            $taxPercent = (float) setting('invoice_gst_percent', '18');
            $subtotal = (float) ($service['filing_fee'] ?? 0);
            $taxAmount = $subtotal * ($taxPercent / 100);
            $total = $subtotal + $taxAmount;

            Invoice::insert([
                'order_id' => $orderId,
                'client_id' => $clientId,
                'invoice_no' => Invoice::nextInvoiceNo(),
                'issue_date' => date('Y-m-d'),
                'due_date' => date('Y-m-d'),
                'subtotal' => $subtotal,
                'tax_percent' => $taxPercent,
                'tax_amount' => $taxAmount,
                'total_amount' => $total,
                'paid_amount' => 0,
                'status' => 'unpaid',
                'notes' => 'Auto-generated on order creation. Payment pending.',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return $orderId;
        });

        activity_log(
            (int) ($user['id'] ?? 0),
            $orderId,
            'order.created_waiting_for_payment',
            'Client placed a new service order. Waiting for payment method selection.',
            ['service_id' => $serviceId, 'payment_status' => 'waiting_for_payment']
        );

        $_SESSION['current_order_id'] = $orderId;

        flash('success', 'Order placed successfully. Please choose your payment method.');
        redirect('payment-method?order_id=' . (int) $orderId . '&source=orders');
    }

    private function serviceOrderCurrentFinancialYear(): string
    {
        $year = (int) date('Y');
        $month = (int) date('n');
        $startYear = $month >= 4 ? $year : $year - 1;
        $endYear = $startYear + 1;

        return $startYear . '-' . substr((string) $endYear, -2);
    }

    private function serviceOrderFinancialYears(int $pastYears = 5, int $futureYears = 1): array
    {
        $year = (int) date('Y');
        $month = (int) date('n');
        $currentStartYear = $month >= 4 ? $year : $year - 1;
        $items = [];

        for ($startYear = $currentStartYear + $futureYears; $startYear >= $currentStartYear - $pastYears; $startYear--) {
            $endYear = $startYear + 1;

            $items[] = [
                'value' => $startYear . '-' . substr((string) $endYear, -2),
                'label' => $startYear . '-' . substr((string) $endYear, -2),
                'is_current' => $startYear === $currentStartYear,
            ];
        }

        return $items;
    }

    private function normalizeOrderFinancialYear(string $financialYear): string
    {
        $financialYear = trim($financialYear);

        if (preg_match('/^\d{4}-\d{2}$/', $financialYear) === 1) {
            return $financialYear;
        }

        if (preg_match('/^(\d{4})-(\d{4})$/', $financialYear, $match) === 1) {
            return $match[1] . '-' . substr($match[2], -2);
        }

        return $this->serviceOrderCurrentFinancialYear();
    }

    private function missingRequiredServiceOrderDocuments(array $requirements): array
    {
        $missing = [];

        foreach ($requirements as $requirement) {
            $requirementId = (int) ($requirement['id'] ?? 0);

            if ($requirementId <= 0) {
                continue;
            }

            if ((int) ($requirement['is_required'] ?? 1) !== 1) {
                continue;
            }

            if (!$this->hasUploadedServiceOrderFile($requirementId)) {
                $missing[] = (string) ($requirement['label'] ?? ('Requirement #' . $requirementId));
            }
        }

        return $missing;
    }

    private function hasUploadedServiceOrderFile(int $requirementId): bool
    {
        $inputName = 'requirement_' . $requirementId;

        if (!isset($_FILES[$inputName]) || !is_array($_FILES[$inputName])) {
            return false;
        }

        foreach ($this->normalizeServiceOrderUploadFiles($_FILES[$inputName]) as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && !empty($file['tmp_name'])) {
                return true;
            }
        }

        return false;
    }

    private function normalizeServiceOrderUploadFiles(array $file): array
    {
        if (is_array($file['name'] ?? null)) {
            $items = [];

            foreach (array_keys($file['name']) as $index) {
                $items[] = [
                    'name' => $file['name'][$index] ?? '',
                    'type' => $file['type'][$index] ?? '',
                    'tmp_name' => $file['tmp_name'][$index] ?? '',
                    'error' => $file['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $file['size'][$index] ?? 0,
                ];
            }

            return $items;
        }

        return [$file];
    }

    private function columnAllowsNull(string $table, string $column): bool
    {
        try {
            $table = str_replace('`', '', $table);
            $column = str_replace('`', '', $column);

            $stmt = $this->dbPdo()->query('SHOW COLUMNS FROM `' . $table . '`');

            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                if ((string) ($row['Field'] ?? '') === $column) {
                    return strtoupper((string) ($row['Null'] ?? 'NO')) === 'YES';
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

public function serviceDetails(): void
{
    $identifier = trim((string) input('service'));
    $service = ctype_digit($identifier)
        ? Service::find((int) $identifier)
        : Service::findBySlug($identifier);

    if (!$service) {
        flash('error', 'Requested service was not found.');
        redirect('services');
    }

    $serviceId = (int) ($service['id'] ?? 0);
    $pdo = $this->dbPdo();

    $stmt = $pdo->prepare(
        'SELECT *
         FROM service_types
         WHERE service_id = :service_id
           AND is_active = 1
         ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute(['service_id' => $serviceId]);
    $types = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare(
        'SELECT *
         FROM service_requirements
         WHERE service_id = :service_id
           AND is_active = 1
         ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute(['service_id' => $serviceId]);
    $requirements = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare(
        'SELECT *
         FROM service_benefits
         WHERE service_id = :service_id
           AND is_active = 1
         ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute(['service_id' => $serviceId]);
    $benefits = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare(
        'SELECT *
         FROM service_reviews
         WHERE service_id = :service_id
           AND is_active = 1
         ORDER BY sort_order ASC, id DESC'
    );
    $stmt->execute(['service_id' => $serviceId]);
    $reviews = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    $this->view('public/service-details', [
        'title' => ((string) ($service['title'] ?? 'Service')) . ' – Tax Saathi',
        'service' => $service,
        'types' => $types,
        'requirements' => $requirements,
        'benefits' => $benefits,
        'reviews' => $reviews,
    ]);
}
    

    public function calculators(): void
    {
        $result = null;
        $type = (string) input('calc_type', 'income_tax');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($type === 'gst') {
                $result = $this->calculateGst();
            } elseif ($type === 'hra') {
                $result = $this->calculateHra();
            } else {
                $result = $this->calculateIncomeTax((string) input('financial_year', '2025-26'));
            }
        }

        $this->view('public/calculators', [
            'title' => 'Tax Calculators – Tax Saathi',
            'result' => $result,
            'type' => $type,
        ]);
    }

    private function calculateIncomeTax(string $financialYear): array
    {
        $regime = (string) input('regime', 'new');
        $income = (float) input('gross_income', 0);
        $deductions = (float) input('deductions', 0);

        $standardDeduction = (float) setting(
            'calc_standard_deduction_' . $regime,
            $regime === 'new' ? '75000' : '50000'
        );
        $rebateThreshold = (float) setting(
            'calc_rebate_threshold_' . $regime,
            $regime === 'new' ? '700000' : '500000'
        );
        $rebateAmount = (float) setting(
            'calc_rebate_amount_' . $regime,
            $regime === 'new' ? '25000' : '12500'
        );
        $cessPercent = (float) setting('calc_cess_percent', '4');
        $rules = TaxRule::byFinancialYear($financialYear, $regime);

        $taxable = max(0, $income - $deductions - $standardDeduction);
        $tax = 0.0;

        foreach ($rules as $rule) {
            $from = (float) $rule['income_from'];
            $to = $rule['income_to'] !== null ? (float) $rule['income_to'] : null;

            if ($taxable <= $from) {
                continue;
            }

            $portion = $to === null
                ? ($taxable - $from)
                : min($taxable, $to) - $from;

            if ($portion > 0) {
                $tax += $portion * ((float) $rule['rate_percent'] / 100);
            }
        }

        $rebate = $taxable <= $rebateThreshold ? min($tax, $rebateAmount) : 0.0;
        $taxAfterRebate = max(0, $tax - $rebate);
        $cess = $taxAfterRebate * ($cessPercent / 100);
        $total = $taxAfterRebate + $cess;

        return [
            'title' => 'Income Tax Calculation',
            'type' => 'income_tax',
            'regime' => ucfirst($regime),
            'gross_income' => $income,
            'deductions' => $deductions,
            'standard_deduction' => $standardDeduction,
            'taxable_income' => $taxable,
            'income_tax' => $tax,
            'rebate' => $rebate,
            'cess' => $cess,
            'total_tax' => $total,
        ];
    }

    private function calculateGst(): array
    {
        $amount = (float) input('amount', 0);
        $rate = (float) input('gst_rate', 18);
        $mode = (string) input('gst_mode', 'exclusive');

        if ($mode === 'inclusive') {
            $base = $amount / (1 + ($rate / 100));
            $gstAmount = $amount - $base;
            $total = $amount;
        } else {
            $base = $amount;
            $gstAmount = $amount * ($rate / 100);
            $total = $base + $gstAmount;
        }

        return [
            'title' => 'GST Calculation',
            'type' => 'gst',
            'base_amount' => $base,
            'gst_amount' => $gstAmount,
            'total_amount' => $total,
        ];
    }

    private function calculateHra(): array
    {
        $basic = (float) input('basic_salary', 0);
        $hraReceived = (float) input('hra_received', 0);
        $rentPaid = (float) input('rent_paid', 0);
        $cityType = (string) input('city_type', 'metro');

        $salaryPercent = $basic * ($cityType === 'metro' ? 0.50 : 0.40);
        $rentMinusTenPercent = max(0, $rentPaid - ($basic * 0.10));
        $exempt = min($hraReceived, $salaryPercent, $rentMinusTenPercent);

        return [
            'title' => 'HRA Calculation',
            'type' => 'hra',
            'hra_exempt' => $exempt,
            'taxable_hra' => max(0, $hraReceived - $exempt),
        ];
    }
    public function contact(): void
{
    $requestedService = trim((string) ($_GET['service'] ?? ''));
    $resolvedService = $this->resolveServiceName($requestedService);

    $old = $this->pullSession('contact_old', []);
    if (!is_array($old)) {
        $old = [];
    }

    if (($old['service'] ?? '') === '' && $resolvedService !== '') {
        $old['service'] = $resolvedService;
    }

    $this->view('home/contact', [
        'pageTitle'        => 'Contact Us',
        'flashSuccess'     => $this->pullSession('contact_success'),
        'flashError'       => $this->pullSession('contact_error'),
        'validationErrors' => $this->pullSession('contact_errors', []),
        'old'              => $old,
        'prefilledService' => $resolvedService,
        'companyEmail'     => 'support@taxsaathi.in',
        'companyPhone'     => '+91 00000 00000',
        'companyHours'     => 'Mon - Sat, 9 AM - 6 PM',
        'companyAddress'   => 'Tax Saathi Office Address Here, City, State, PIN Code',
    ]);
}

public function contactSubmit(): void
{
    if (function_exists('verify_csrf')) {
        verify_csrf();
    }

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $phoneRaw = trim((string) ($_POST['phone'] ?? ''));
    $phone = preg_replace('/[^0-9+]/', '', $phoneRaw) ?? '';
    $email = trim((string) ($_POST['email'] ?? ''));
    $service = trim((string) ($_POST['service'] ?? ''));
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    $old = [
        'full_name' => $fullName,
        'phone'     => $phoneRaw,
        'email'     => $email,
        'service'   => $service,
        'subject'   => $subject,
        'message'   => $message,
    ];

    $errors = [];

    if ($fullName === '' || mb_strlen($fullName) < 2 || mb_strlen($fullName) > 120) {
        $errors['full_name'] = 'Please enter a valid full name.';
    }

    if ($phone === '' || strlen($phone) < 7 || strlen($phone) > 20) {
        $errors['phone'] = 'Please enter a valid phone number.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if ($service !== '' && mb_strlen($service) > 150) {
        $errors['service'] = 'Service field is too long.';
    }

    if ($subject === '' || mb_strlen($subject) < 3 || mb_strlen($subject) > 180) {
        $errors['subject'] = 'Please enter a valid subject.';
    }

    if ($message === '' || mb_strlen($message) < 10 || mb_strlen($message) > 5000) {
        $errors['message'] = 'Message must be between 10 and 5000 characters.';
    }

    if ($errors !== []) {
        $this->flashSession('contact_errors', $errors);
        $this->flashSession('contact_old', $old);
        $this->redirectToContact($service);
    }

    try {
        Lead::insert([
            'name'        => $fullName,
            'phone'       => $phone,
            'email'       => $email,
            'service'     => $service,
            'message'     => "Subject: {$subject}\n\n{$message}",
            'status'      => 'new',
            'source'      => 'website',
            'assigned_to' => null,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->flashSession('contact_success', 'Thank you. Your enquiry has been submitted successfully.');
        $this->redirectToContact($service);
    } catch (\Throwable $e) {
        $this->flashSession('contact_error', 'Database error: ' . $e->getMessage());
        $this->flashSession('contact_old', $old);
        $this->redirectToContact($service);
    }
}
private function resolveServiceName(string $serviceParam): string
{
    $serviceParam = trim($serviceParam);

    if ($serviceParam === '') {
        return '';
    }

    try {
        $pdo = $this->db();

        if (ctype_digit($serviceParam)) {
            $stmt = $pdo->prepare("SELECT title FROM services WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => (int) $serviceParam]);
        } else {
            $stmt = $pdo->prepare("SELECT title FROM services WHERE slug = :slug LIMIT 1");
            $stmt->execute([':slug' => $serviceParam]);
        }

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (is_array($row) && !empty($row['title'])) {
            return trim((string) $row['title']);
        }
    } catch (\Throwable $e) {
        // silently ignore and fall back
    }

    return $serviceParam;
}

private function db(): \PDO
{
    return $this->dbPdo();
}

private function flashSession(string $key, mixed $value): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $_SESSION[$key] = $value;
}

private function pullSession(string $key, mixed $default = null): mixed
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!array_key_exists($key, $_SESSION)) {
        return $default;
    }

    $value = $_SESSION[$key];
    unset($_SESSION[$key]);

    return $value;
}

private function redirectToContact(string $service = ''): never
{
    $url = rtrim(base_url('contact'), '/');

    if ($service !== '') {
        $url .= '?service=' . urlencode($service);
    }

    $url .= '#contact-section';

    header('Location: ' . $url);
    exit;
}

private function getIpAddress(): ?string
{
    $keys = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR',
    ];

    foreach ($keys as $key) {
        $value = trim((string) ($_SERVER[$key] ?? ''));
        if ($value === '') {
            continue;
        }

        if ($key === 'HTTP_X_FORWARDED_FOR') {
            $parts = explode(',', $value);
            $value = trim($parts[0] ?? '');
        }

        if ($value !== '') {
            return substr($value, 0, 45);
        }
    }

    return null;
}

}