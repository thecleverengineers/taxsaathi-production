<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use DomainException;
use RuntimeException;
use Throwable;

require_once dirname(__DIR__) . '/helpers/user_roles_rbac_helper.php';

/**
 * Executive management, workflow monitoring, and manual salary ledger.
 *
 * Executive identity and access are always resolved through:
 * user_roles.user_id -> user_roles.role_id = 3.
 *
 * Administrators and Managers can view the module. Only Administrators can
 * edit Executive profiles, change account status, or record salary payments.
 */
final class AdminExecutiveController extends Controller
{
    private const EXECUTIVE_ROLE_ID = 3;
    private const ADMIN_ROLE_ID = 1;
    private const MANAGER_ROLE_ID = 2;
    private const PERIOD_TYPES = ['week', 'month', 'year'];
    private const SALARY_METHODS = ['bank_transfer', 'upi', 'cash'];

    private function db(): Database
    {
        $db = app('db');

        if (!$db instanceof Database) {
            throw new RuntimeException('Database connection is unavailable.');
        }

        return $db;
    }

    private function currentUserId(): int
    {
        return rbac_current_user_id();
    }

    private function hasRole(int $roleId): bool
    {
        $context = rbac_context($this->currentUserId(), true);
        $roleIds = is_array($context['role_ids'] ?? null)
            ? $context['role_ids']
            : [];

        return in_array($roleId, array_map('intval', $roleIds), true);
    }

    private function requireViewAccess(): void
    {
        require_auth();

        $userId = $this->currentUserId();
        $isAdminOrManager = $this->hasRole(self::ADMIN_ROLE_ID)
            || $this->hasRole(self::MANAGER_ROLE_ID);

        if (
            $userId <= 0
            || !$isAdminOrManager
            || !rbac_can('executives.view', $userId)
        ) {
            http_response_code(403);
            exit('Administrator or Manager access required.');
        }
    }

    private function requireAdminMutation(string $permission): void
    {
        require_auth();

        $userId = $this->currentUserId();

        if (
            $userId <= 0
            || !$this->hasRole(self::ADMIN_ROLE_ID)
            || !rbac_can($permission, $userId)
        ) {
            http_response_code(403);
            exit('Administrator access required.');
        }
    }

    private function executiveExists(int $executiveId): bool
    {
        if ($executiveId <= 0) {
            return false;
        }

        $row = $this->db()->fetch(
            'SELECT u.id
             FROM users u
             INNER JOIN user_roles ur
                ON ur.user_id = u.id
               AND ur.role_id = :executive_role_id
             WHERE u.id = :executive_user_id
             LIMIT 1',
            [
                'executive_role_id' => self::EXECUTIVE_ROLE_ID,
                'executive_user_id' => $executiveId,
            ]
        );

        return is_array($row) && (int) ($row['id'] ?? 0) === $executiveId;
    }

    private function salaryTableReady(): bool
    {
        return rbac_table_exists('executive_salary_payments');
    }

    private function paymentProfileTableReady(): bool
    {
        return rbac_table_exists('executive_payment_profiles');
    }

    private function paymentProfile(int $executiveId): ?array
    {
        if (!$this->paymentProfileTableReady() || $executiveId <= 0) {
            return null;
        }

        return $this->db()->fetch(
            'SELECT epp.*
             FROM executive_payment_profiles epp
             INNER JOIN user_roles ur
                ON ur.user_id = epp.executive_user_id
               AND ur.role_id = :executive_role_id
             WHERE epp.executive_user_id = :executive_user_id
             LIMIT 1',
            [
                'executive_role_id' => self::EXECUTIVE_ROLE_ID,
                'executive_user_id' => $executiveId,
            ]
        );
    }

    private function periodRange(string $periodType, string $anchor): array
    {
        $periodType = strtolower(trim($periodType));

        if (!in_array($periodType, self::PERIOD_TYPES, true)) {
            $periodType = 'month';
        }

        $anchor = trim($anchor);
        $date = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $anchor !== '' ? $anchor : date('Y-m-d')
        );
        $errors = \DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || (
                is_array($errors)
                && (($errors['warning_count'] ?? 0) > 0
                    || ($errors['error_count'] ?? 0) > 0)
            )
        ) {
            throw new DomainException('Please choose a valid period date.');
        }

        if ($periodType === 'week') {
            $start = $date->modify('monday this week');
            $endExclusive = $start->modify('+7 days');
            $label = 'Week of ' . $start->format('d M Y');
        } elseif ($periodType === 'year') {
            $start = $date->setDate((int) $date->format('Y'), 1, 1);
            $endExclusive = $start->modify('+1 year');
            $label = $start->format('Y');
        } else {
            $start = $date->modify('first day of this month');
            $endExclusive = $start->modify('+1 month');
            $label = $start->format('F Y');
        }

        return [
            'type' => $periodType,
            'anchor' => $date->format('Y-m-d'),
            'start' => $start->format('Y-m-d'),
            'end' => $endExclusive->modify('-1 day')->format('Y-m-d'),
            'end_exclusive' => $endExclusive->format('Y-m-d'),
            'label' => $label,
        ];
    }

    private function parseAmount(mixed $value): string
    {
        $raw = trim(str_replace(',', '', (string) $value));

        if ($raw === '' || preg_match('/^\d+(?:\.\d{1,2})?$/', $raw) !== 1) {
            throw new DomainException('Enter a valid salary amount with up to two decimal places.');
        }

        $amount = (float) $raw;

        if ($amount <= 0 || $amount > 999999999.99) {
            throw new DomainException('Salary amount must be greater than zero.');
        }

        return number_format($amount, 2, '.', '');
    }

    private function executiveList(string $search, string $status): array
    {
        $where = [];
        $params = [
            'executive_role_id' => self::EXECUTIVE_ROLE_ID,
        ];

        if ($search !== '') {
            $where[] = '(
                u.name LIKE :name_search
                OR u.email LIKE :email_search
                OR u.phone LIKE :phone_search
            )';
            $like = '%' . $search . '%';
            $params['name_search'] = $like;
            $params['email_search'] = $like;
            $params['phone_search'] = $like;
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $where[] = 'u.is_active = :is_active';
            $params['is_active'] = $status === 'active' ? 1 : 0;
        }

        $payoutJoin = rbac_table_exists('executive_order_payouts')
            ? 'LEFT JOIN (
                    SELECT executive_user_id,
                           COALESCE(SUM(CASE WHEN status = \'paid\' THEN amount ELSE 0 END), 0) AS order_payout_total
                    FROM executive_order_payouts
                    GROUP BY executive_user_id
               ) ep ON ep.executive_user_id = u.id'
            : 'LEFT JOIN (SELECT 0 AS executive_user_id, 0.00 AS order_payout_total) ep
               ON ep.executive_user_id = u.id';

        $salaryJoin = $this->salaryTableReady()
            ? 'LEFT JOIN (
                    SELECT executive_user_id,
                           COALESCE(SUM(CASE WHEN status = \'paid\' THEN amount ELSE 0 END), 0) AS salary_total
                    FROM executive_salary_payments
                    GROUP BY executive_user_id
               ) es ON es.executive_user_id = u.id'
            : 'LEFT JOIN (SELECT 0 AS executive_user_id, 0.00 AS salary_total) es
               ON es.executive_user_id = u.id';

        $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        return $this->db()->fetchAll(
            'SELECT
                u.id,
                u.name,
                u.phone,
                u.email,
                u.is_active,
                u.created_at,
                u.last_login_at,
                COUNT(DISTINCT o.id) AS assigned_orders,
                COUNT(DISTINCT CASE
                    WHEN LOWER(COALESCE(o.status, \'\')) IN
                        (\'completed\', \'complete\', \'closed\', \'delivered\')
                    THEN o.id
                END) AS completed_orders,
                COUNT(DISTINCT CASE
                    WHEN LOWER(COALESCE(o.status, \'\')) NOT IN
                        (\'completed\', \'complete\', \'closed\', \'delivered\', \'rejected\')
                    THEN o.id
                END) AS active_orders,
                COALESCE(ep.order_payout_total, 0) AS order_payout_total,
                COALESCE(es.salary_total, 0) AS salary_total
             FROM users u
             INNER JOIN user_roles executive_role
                ON executive_role.user_id = u.id
               AND executive_role.role_id = :executive_role_id
             LEFT JOIN orders o ON o.assigned_user_id = u.id
             ' . $payoutJoin . '
             ' . $salaryJoin . '
             ' . $whereSql . '
             GROUP BY
                u.id, u.name, u.phone, u.email, u.is_active,
                u.created_at, u.last_login_at,
                ep.order_payout_total, es.salary_total
             ORDER BY u.is_active DESC, u.name ASC, u.id ASC',
            $params
        ) ?: [];
    }

    private function findExecutive(int $executiveId): ?array
    {
        return $this->db()->fetch(
            'SELECT
                u.id,
                u.name,
                u.phone,
                u.email,
                u.is_active,
                u.created_at,
                u.updated_at,
                u.last_login_at,
                COALESCE(
                    (
                        SELECT GROUP_CONCAT(
                            DISTINCT r.name
                            ORDER BY r.id ASC
                            SEPARATOR \', \'
                        )
                        FROM user_roles all_roles
                        INNER JOIN roles r ON r.id = all_roles.role_id
                        WHERE all_roles.user_id = u.id
                    ),
                    \'Executive\'
                ) AS role_names
             FROM users u
             INNER JOIN user_roles executive_role
                ON executive_role.user_id = u.id
               AND executive_role.role_id = :executive_role_id
             WHERE u.id = :executive_user_id
             LIMIT 1',
            [
                'executive_role_id' => self::EXECUTIVE_ROLE_ID,
                'executive_user_id' => $executiveId,
            ]
        );
    }

    private function workflowSummary(
        int $executiveId,
        array $period
    ): array {
        $groups = $this->db()->fetchAll(
            'SELECT
                LOWER(COALESCE(o.status, \'\')) AS workflow_status,
                COUNT(*) AS total,
                COALESCE(SUM(o.fee_amount), 0) AS order_value
             FROM orders o
             INNER JOIN user_roles executive_role
                ON executive_role.user_id = o.assigned_user_id
               AND executive_role.role_id = :executive_role_id
             WHERE o.assigned_user_id = :executive_user_id
               AND COALESCE(o.updated_at, o.created_at) >= :period_start
               AND COALESCE(o.updated_at, o.created_at) < :period_end
             GROUP BY LOWER(COALESCE(o.status, \'\'))
             ORDER BY total DESC',
            [
                'executive_role_id' => self::EXECUTIVE_ROLE_ID,
                'executive_user_id' => $executiveId,
                'period_start' => $period['start'] . ' 00:00:00',
                'period_end' => $period['end_exclusive'] . ' 00:00:00',
            ]
        );

        $summary = [
            'total_orders' => 0,
            'active_orders' => 0,
            'completed_orders' => 0,
            'rejected_orders' => 0,
            'order_value' => 0.0,
            'completed_value' => 0.0,
            'completion_rate' => 0.0,
            'statuses' => [],
        ];

        $completedStatuses = ['completed', 'complete', 'closed', 'delivered'];

        foreach ($groups as $group) {
            $status = trim((string) ($group['workflow_status'] ?? '')) ?: 'unknown';
            $total = (int) ($group['total'] ?? 0);
            $value = (float) ($group['order_value'] ?? 0);

            $summary['total_orders'] += $total;
            $summary['order_value'] += $value;
            $summary['statuses'][$status] = $total;

            if (in_array($status, $completedStatuses, true)) {
                $summary['completed_orders'] += $total;
                $summary['completed_value'] += $value;
            } elseif ($status === 'rejected') {
                $summary['rejected_orders'] += $total;
            } else {
                $summary['active_orders'] += $total;
            }
        }

        if ($summary['total_orders'] > 0) {
            $summary['completion_rate'] = round(
                ($summary['completed_orders'] / $summary['total_orders']) * 100,
                1
            );
        }

        return $summary;
    }

    private function workflowRows(int $executiveId, array $period): array
    {
        return $this->db()->fetchAll(
            'SELECT
                o.id,
                o.order_no,
                o.status,
                o.fee_amount,
                o.completed_at,
                o.created_at,
                o.updated_at,
                COALESCE(NULLIF(c.company_name, \'\'), NULLIF(c.name, \'\'), \'Client\') AS client_name,
                COALESCE(NULLIF(s.title, \'\'), \'Service\') AS service_title
             FROM orders o
             INNER JOIN user_roles executive_role
                ON executive_role.user_id = o.assigned_user_id
               AND executive_role.role_id = :executive_role_id
             LEFT JOIN clients c ON c.id = o.client_id
             LEFT JOIN services s ON s.id = o.service_id
             WHERE o.assigned_user_id = :executive_user_id
               AND COALESCE(o.updated_at, o.created_at) >= :period_start
               AND COALESCE(o.updated_at, o.created_at) < :period_end
             ORDER BY COALESCE(o.updated_at, o.created_at) DESC, o.id DESC
             LIMIT 100',
            [
                'executive_role_id' => self::EXECUTIVE_ROLE_ID,
                'executive_user_id' => $executiveId,
                'period_start' => $period['start'] . ' 00:00:00',
                'period_end' => $period['end_exclusive'] . ' 00:00:00',
            ]
        ) ?: [];
    }

    private function salaryRows(int $executiveId): array
    {
        if (!$this->salaryTableReady()) {
            return [];
        }

        return $this->db()->fetchAll(
            'SELECT
                sp.*,
                COALESCE(NULLIF(payer.name, \'\'), CONCAT(\'Administrator #\', sp.paid_by_user_id)) AS paid_by_name
             FROM executive_salary_payments sp
             LEFT JOIN users payer ON payer.id = sp.paid_by_user_id
             WHERE sp.executive_user_id = :executive_user_id
             ORDER BY sp.period_start DESC, sp.id DESC',
            ['executive_user_id' => $executiveId]
        ) ?: [];
    }

    private function orderPayoutRows(int $executiveId): array
    {
        if (!rbac_table_exists('executive_order_payouts')) {
            return [];
        }

        return $this->db()->fetchAll(
            'SELECT
                p.amount,
                p.currency,
                p.payment_method,
                p.payment_reference,
                p.paid_at,
                o.order_no
             FROM executive_order_payouts p
             LEFT JOIN orders o ON o.id = p.order_id
             WHERE p.executive_user_id = :executive_user_id
               AND p.status = \'paid\'
             ORDER BY p.paid_at DESC, p.id DESC
             LIMIT 100',
            ['executive_user_id' => $executiveId]
        ) ?: [];
    }

    public function index(): void
    {
        $this->requireViewAccess();

        $search = trim((string) input('q', ''));
        $status = strtolower(trim((string) input('status', 'all')));
        if (!in_array($status, ['all', 'active', 'inactive'], true)) {
            $status = 'all';
        }

        $executives = $this->executiveList($search, $status);
        $stats = [
            'total' => count($executives),
            'active' => 0,
            'assigned_orders' => 0,
            'completed_orders' => 0,
        ];

        foreach ($executives as $executive) {
            $stats['active'] += (int) ($executive['is_active'] ?? 0) === 1 ? 1 : 0;
            $stats['assigned_orders'] += (int) ($executive['assigned_orders'] ?? 0);
            $stats['completed_orders'] += (int) ($executive['completed_orders'] ?? 0);
        }

        $this->view('admin/executives', [
            'title' => 'Executive Management – Tax Saathi',
            'executives' => $executives,
            'stats' => $stats,
            'filters' => ['q' => $search, 'status' => $status],
            'canManage' => $this->hasRole(self::ADMIN_ROLE_ID)
                && rbac_can('executives.manage', $this->currentUserId()),
        ], 'layouts/dashboard');
    }

    public function show(): void
    {
        $this->requireViewAccess();

        $executiveId = (int) input('id', 0);
        $executive = $this->findExecutive($executiveId);

        if (!$executive) {
            flash('error', 'Executive user not found.');
            redirect('admin/executives');
        }

        try {
            $period = $this->periodRange(
                (string) input('period_type', 'month'),
                (string) input('period_anchor', date('Y-m-d'))
            );
        } catch (DomainException $e) {
            $period = $this->periodRange('month', date('Y-m-d'));
        }

        $canViewPaymentDetails = $this->hasRole(self::ADMIN_ROLE_ID);

        $this->view('admin/executive-profile', [
            'title' => (string) ($executive['name'] ?? 'Executive') . ' – Executive Profile',
            'executive' => $executive,
            'period' => $period,
            'workflowSummary' => $this->workflowSummary($executiveId, $period),
            'workflowRows' => $this->workflowRows($executiveId, $period),
            'salaryRows' => $this->salaryRows($executiveId),
            'orderPayoutRows' => $this->orderPayoutRows($executiveId),
            'salaryMethods' => self::SALARY_METHODS,
            'salaryTableReady' => $this->salaryTableReady(),
            'paymentProfileTableReady' => $this->paymentProfileTableReady(),
            'paymentProfile' => $canViewPaymentDetails ? $this->paymentProfile($executiveId) : null,
            'canViewPaymentDetails' => $canViewPaymentDetails,
            'canManage' => $this->hasRole(self::ADMIN_ROLE_ID)
                && rbac_can('executives.manage', $this->currentUserId()),
            'canPaySalary' => $this->hasRole(self::ADMIN_ROLE_ID)
                && rbac_can('executives.salary.manage', $this->currentUserId()),
        ], 'layouts/dashboard');
    }

    public function update(): void
    {
        $this->requireAdminMutation('executives.manage');
        verify_csrf();

        $executiveId = (int) input('id', 0);
        if (!$this->executiveExists($executiveId)) {
            flash('error', 'Executive user not found.');
            redirect('admin/executives');
        }

        $name = trim((string) input('name', ''));
        $phoneInput = trim((string) input('phone', ''));
        $phone = function_exists('normalize_phone')
            ? normalize_phone($phoneInput)
            : $phoneInput;
        $email = strtolower(trim((string) input('email', '')));

        if ($name === '' || $phone === '') {
            flash('error', 'Executive name and phone are required.');
            redirect('admin/executives/show?id=' . $executiveId);
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter a valid Executive email address.');
            redirect('admin/executives/show?id=' . $executiveId);
        }

        $db = $this->db();
        $phoneExists = $db->fetch(
            'SELECT id FROM users WHERE phone = :phone AND id <> :user_id LIMIT 1',
            ['phone' => $phone, 'user_id' => $executiveId]
        );

        if ($phoneExists) {
            flash('error', 'This phone number is already used by another user.');
            redirect('admin/executives/show?id=' . $executiveId);
        }

        if ($email !== '') {
            $emailExists = $db->fetch(
                'SELECT id FROM users WHERE email = :email AND id <> :user_id LIMIT 1',
                ['email' => $email, 'user_id' => $executiveId]
            );

            if ($emailExists) {
                flash('error', 'This email address is already used by another user.');
                redirect('admin/executives/show?id=' . $executiveId);
            }
        }

        $db->execute(
            'UPDATE users
             SET name = :name,
                 phone = :phone,
                 email = :email,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'name' => $name,
                'phone' => $phone,
                'email' => $email !== '' ? $email : null,
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $executiveId,
            ]
        );

        flash('success', 'Executive profile updated successfully.');
        redirect('admin/executives/show?id=' . $executiveId);
    }

    public function toggle(): void
    {
        $this->requireAdminMutation('executives.manage');
        verify_csrf();

        $executiveId = (int) input('id', 0);
        if (!$this->executiveExists($executiveId)) {
            flash('error', 'Executive user not found.');
            redirect('admin/executives');
        }

        if ($executiveId === $this->currentUserId()) {
            flash('error', 'You cannot disable your own account.');
            redirect('admin/executives/show?id=' . $executiveId);
        }

        $user = $this->db()->fetch(
            'SELECT is_active FROM users WHERE id = :id LIMIT 1',
            ['id' => $executiveId]
        );

        $this->db()->execute(
            'UPDATE users
             SET is_active = :is_active, updated_at = :updated_at
             WHERE id = :id',
            [
                'is_active' => (int) ($user['is_active'] ?? 0) === 1 ? 0 : 1,
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $executiveId,
            ]
        );

        flash('success', 'Executive account status updated.');
        redirect('admin/executives/show?id=' . $executiveId);
    }

    public function paySalary(): void
    {
        $this->requireAdminMutation('executives.salary.manage');
        verify_csrf();

        $executiveId = (int) input('executive_user_id', 0);
        if (!$this->executiveExists($executiveId)) {
            flash('error', 'Executive user not found.');
            redirect('admin/executives');
        }

        if (!$this->salaryTableReady()) {
            flash('error', 'Salary records are not installed. Run the latest database migration first.');
            redirect('admin/executives/show?id=' . $executiveId);
        }

        try {
            $amount = $this->parseAmount(input('amount', ''));
            $period = $this->periodRange(
                (string) input('period_type', 'month'),
                (string) input('period_anchor', date('Y-m-d'))
            );
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
            redirect('admin/executives/show?id=' . $executiveId);
        }

        $paymentMethod = strtolower(trim((string) input('payment_method', '')));
        $paymentReference = trim((string) input('payment_reference', ''));
        $notes = trim((string) input('notes', ''));

        if (!in_array($paymentMethod, self::SALARY_METHODS, true)) {
            flash('error', 'Please select a valid salary payment method.');
            redirect('admin/executives/show?id=' . $executiveId);
        }

        if (strlen($paymentReference) > 120) {
            $paymentReference = substr($paymentReference, 0, 120);
        }

        if (strlen($notes) > 4000) {
            $notes = substr($notes, 0, 4000);
        }

        $adminUserId = $this->currentUserId();
        $paidAt = date('Y-m-d H:i:s');

        try {
            $this->db()->transaction(function (Database $db) use (
                $executiveId,
                $amount,
                $period,
                $paymentMethod,
                $paymentReference,
                $notes,
                $adminUserId,
                $paidAt
            ): void {
                $existing = $db->fetch(
                    'SELECT id
                     FROM executive_salary_payments
                     WHERE executive_user_id = :executive_user_id
                       AND period_type = :period_type
                       AND period_start = :period_start
                       AND period_end = :period_end
                     LIMIT 1
                     FOR UPDATE',
                    [
                        'executive_user_id' => $executiveId,
                        'period_type' => $period['type'],
                        'period_start' => $period['start'],
                        'period_end' => $period['end'],
                    ]
                );

                if ($existing) {
                    throw new DomainException(
                        'Salary for this Executive and period has already been recorded.'
                    );
                }

                $db->execute(
                    'INSERT INTO executive_salary_payments
                    (
                        executive_user_id,
                        period_type,
                        period_start,
                        period_end,
                        amount,
                        currency,
                        status,
                        payment_method,
                        payment_reference,
                        notes,
                        paid_by_user_id,
                        paid_at,
                        created_at,
                        updated_at
                    )
                    VALUES
                    (
                        :executive_user_id,
                        :period_type,
                        :period_start,
                        :period_end,
                        :amount,
                        :currency,
                        :status,
                        :payment_method,
                        :payment_reference,
                        :notes,
                        :paid_by_user_id,
                        :paid_at,
                        :created_at,
                        :updated_at
                    )',
                    [
                        'executive_user_id' => $executiveId,
                        'period_type' => $period['type'],
                        'period_start' => $period['start'],
                        'period_end' => $period['end'],
                        'amount' => $amount,
                        'currency' => 'INR',
                        'status' => 'paid',
                        'payment_method' => $paymentMethod,
                        'payment_reference' => $paymentReference !== '' ? $paymentReference : null,
                        'notes' => $notes !== '' ? $notes : null,
                        'paid_by_user_id' => $adminUserId,
                        'paid_at' => $paidAt,
                        'created_at' => $paidAt,
                        'updated_at' => $paidAt,
                    ]
                );
            });

            activity_log(
                $adminUserId,
                null,
                'executive.salary.paid',
                'Paid INR ' . number_format((float) $amount, 2)
                    . ' salary to Executive #' . $executiveId
                    . ' for ' . $period['label'] . '.',
                [
                    'executive_user_id' => $executiveId,
                    'period_type' => $period['type'],
                    'period_start' => $period['start'],
                    'period_end' => $period['end'],
                    'amount' => $amount,
                    'currency' => 'INR',
                    'payment_method' => $paymentMethod,
                    'payment_reference' => $paymentReference,
                ]
            );

            flash('success', 'Executive salary payment recorded successfully.');
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
        } catch (Throwable $e) {
            error_log('Executive salary payment failed: ' . $e->getMessage());
            flash('error', 'Salary payment could not be recorded. No payment was saved.');
        }

        redirect(
            'admin/executives/show?id=' . $executiveId
            . '&period_type=' . urlencode($period['type'])
            . '&period_anchor=' . urlencode($period['anchor'])
        );
    }
}
